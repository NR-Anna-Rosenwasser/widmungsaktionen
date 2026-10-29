# Widmungsaktionen – Specification

Status: draft v1 (2026-10-15). Living document – update it when decisions change.

## 1. Purpose

Authors (starting with Anna Rosenwasser) regularly run **Widmungsaktionen**: readers order a signed, personally dedicated copy of a book, the author signs all orders of one Aktion in a single session, and the books are shipped together.

The app replaces one-off forms with a permanent storefront:

- There is always an open or upcoming Aktion, so readers never have to "wait for the next one".
- Readers see when their book will roughly arrive, pay online or by invoice, and follow their order through a personal link.
- The team manages orders, payments, moderation, signing, printing and shipping in one backend.
- Multiple sellers (tenants) can use the same installation, e.g. a publisher for several authors.

## 2. Glossary

| Term | Meaning |
|---|---|
| Tenant / Seller | The legal seller: address, bank account, Stripe account, settings. Anna is the first tenant. |
| Book | A title a tenant sells (with author, price, weight). |
| Aktion | One signing round: order window, signing date, estimated shipping date, selection of books. |
| Order | One purchase by one person, shipped to **one** address. 1–4 copies. |
| Copy (order item) | One physical book within an order. Each copy has its own dedication. |
| Widmung | The dedication. "Gewidmet" = signed. |
| Stapelnummer | Per-Aktion sequence number (1…n) of an order, written on the post-it during signing. |

## 3. Domains & tenancy

- Admin panel: `admin.widmungen.rose-water.ch` (Filament, Laravel).
- Platform storefront: `widmungen.rose-water.ch/{tenant-slug}` (Nuxt).
- Bare `widmungen.rose-water.ch`: if only one tenant is active, redirect straight to its storefront; otherwise show a simple seller picker (name, author(s), current Aktion).
- Custom domains: a tenant can point its own domain (e.g. `widmungen.example.ch`) to the app. A request on a custom domain goes straight to that tenant's storefront without a slug.
- Tenant resolution happens in Laravel from the forwarded host (custom domain table) or the slug.
- No self-service signup. Super admins create tenants through a **"Neuer Shop" wizard** in Filament: shop and languages → seller data → bank/QR → Stripe keys → shipping → first admin user.

## 4. Data model (overview)

```
Tenant ─┬─ TenantDomain
        ├─ TenantSettings (seller address, IBAN/QR type, Stripe keys [encrypted],
        │                  mail sender, shipping tiers, max copies, payment methods,
        │                  print settings, newsletter webhook, branding, contact email)
        ├─ Book (author_name, title, subtitle, cover, price, isbn, weight_g, active)
        ├─ Aktion ── aktion_book (book, optional price override)
        └─ Order ─┬─ OrderItem (book, recipient_name, wish, moderation_status,
                  │             position, signed_at, signed_by)
                  ├─ Payment (method, amount, status, stripe ids, qr_reference,
                  │           paid_at, refunded_amount)
                  ├─ Shipment (shipped_at, carrier, tracking_number nullable)
                  └─ OrderEvent (timeline/audit log, drives the tracking page)
User ── tenant_user (role per tenant)
SigningSession (aktion, user, cursor, checkpoint log)
```

### Aktion

- Fields: name, `orders_open_at`, `order_deadline`, `signing_date`, `estimated_shipping_date`, optional `capacity` (max copies), status `draft | open | closed | signing | shipping | done`.
- Selected books (subset of the tenant's books).
- **At most one Aktion per tenant is `open` at a time** (enforced on save and by a partial unique index). The storefront shows that Aktion; if none is open, it shows the next scheduled one with its opening date.
- After an Aktion closes, unpaid pay-in-advance orders (one email reminder before the deadline) are handled per the shop setting `unpaid_after_deadline`:
  - `manual` (default; existing shops were switched by migration): nothing happens automatically. They appear in «Offene Bestellungen» and can still be paid until someone decides.
  - `move`: moved once to the next Aktion, cancelled the second time.
  - `cancel`: cancelled right away.
- Moving an order to another Aktion can also be done by hand, as long as none of its books is signed; it loses its post-it number.

### Testing mode

A switch on the Aktion, separate from the status, so the automatic status changes can be tested too. Typical use: set up the real Aktion in testing mode, click through everything, delete the test orders, end testing mode.

- **Access:** a secret link `…/vorschau/<token>` (no login). It stores the token in a session cookie (`widmungen_preview`); the Nuxt server forwards it as `X-Widmungen-Preview` during SSR. With it, the storefront shows only that test Aktion, with a banner and «Testmodus verlassen». «Neuen Link erzeugen» invalidates old links. Without the link, test Aktionen are invisible.
- **Separate from real Aktionen:** test Aktionen don't count for «one open Aktion per shop» (also in the database index), don't appear on the public storefront or as the dashboard's default, and are marked «(Test)» in admin dropdowns. Orders only move between Aktionen of the same kind (test or real), automatically and by hand.
- **Orders:** own numbers `TEST-<prefix>-<year>-0001` (`tenants.test_order_sequence`); real numbers have no gaps. Test orders can be deleted (single, «Alle Testbestellungen löschen», or with the Aktion); real orders never.
- **Online payment:** never reaches Stripe. The checkout goes to `/testzahlung/<order token>` with «Zahlung erfolgreich / fehlgeschlagen / Abbrechen», which run the same code as the Stripe webhooks. Offered even without Stripe keys. Refunds are booked without moving money.
- **Emails:** all sent for real to the entered address, subject prefixed «[TEST]». Links to continue a draft go through the secret link. «Wartende E-Mails jetzt senden» sends abandoned-order reminders, payment reminders and pending «Gewidmet» mails without waiting.
- **Invoice:** a «TESTRECHNUNG – NICHT BEZAHLEN» band across the payment part, covering the QR code.
- **Newsletter:** opt-ins are not forwarded.
- **Ending testing mode:** only without test orders («Testmodus beenden» offers to delete them) and if no other real Aktion is open; the link stops working.

### Order statuses

`status` (fulfilment) and `payment_status` are separate fields.

| status | meaning |
|---|---|
| `draft` | Form started, email known, not submitted (abandoned-cart candidate) |
| `pending_payment` | Submitted, waiting for online / pay-in-advance payment |
| `confirmed` | Paid, or "invoice in parcel" chosen → ready for signing |
| `signed` | All copies gewidmet |
| `shipped` | Shipment recorded |
| `cancelled` | Cancelled (with reason) |

`payment_status`: `unpaid | paid | partially_refunded | refunded`.

Order number: per tenant, human readable (e.g. `AR-2026-0042`). Stapelnummer: assigned when the signing list of an Aktion is generated.

## 5. Storefront (Nuxt)

### Order form (multi-step, "typeform" style)

One question per screen, large type, progress indicator, back button. State is saved to the server after every step once the email is known.

1. **Bücher** – choose books and quantities, max **4 copies** in total. Hint: "Mehr als 4 Bücher? Schreib uns an {contact_email}." Shows the Aktion timing: order deadline, signing date, estimated delivery.
2. **E-Mail** – a draft order is created here. Short explanation: we store this so we can reach you about your order, including one reminder if you don't finish.
3. **Widmung** (per copy) – for whom (name), optional wish (character limit, default 200), or "nur Unterschrift". Each shop chooses in its settings:
   - «Nur Unterschrift» anbieten (default on). Off: every copy needs a name; drafts that still ask for a plain signature need a name before submitting.
   - Widmungswunsch anbieten (default on). Off: name only. A name without a wish means «lass dich überraschen» (shown that way in the summary, the confirmation email and on the order page, editable under «Texte»).
   - Namen ohne Wunsch automatisch freigeben (default off). Otherwise every copy with a name goes through «Widmungswünsche prüfen»; plain signatures never do. In the admin, a name without a wish shows «Kein Wunsch: frei widmen».
4. **Adresse** – billing address. Both address forms suggest addresses in CH and LI as you type:
   - «Strasse und Nr.»: the most likely addresses (street, number, ZIP, town). Picking one fills ZIP, town and country.
   - «PLZ»: postal code and locality pairs from the official directory (swisstopo). Picking one fills the town.
   - Typing without picking always works. Lookups go through Laravel (self-hosted Photon geocoder, Redis cache), so the browser makes no third-party requests.

   Checkbox "Als Geschenk / an jemand anderes senden" opens:
   - delivery address
   - a message printed on the cover letter (required)
   - a note that the sender's name always appears on the letter (no anonymous deliveries)
   - the hint "Mehrere Empfänger*innen? Bitte pro Adresse eine Bestellung."
5. **Zahlung** – available methods (see §6). "Rechnung im Paket" is disabled for gift orders.
6. **Übersicht** – summary, newsletter opt-in (unchecked), terms/privacy → submit.
   - Online payment → Stripe Checkout → back to the confirmation page.
7. **Danke** – link to the tracking page, button "Noch eine Bestellung für jemand anderen", which pre-fills the buyer's details.

### Tracking page

- Personal link `/{slug}/bestellung/{token}` (unguessable token). Alternative lookup with order number + email.
- Shows a timeline: received → paid → gewidmet → shipped (date, and tracking number once supported), plus the expected shipping date of the Aktion. With «Rechnung im Paket», payment is the last step: received → gewidmet → shipped → paid.
- For unpaid pay-in-advance orders: invoice download and a "jetzt online bezahlen" button.

### Shipping

The shipping tiers and a running total are shown in the book-selection step, and a short note under the books on the start page.

Tiers are configurable per tenant. Defaults:

| Copies | Method | Price |
|---|---|---|
| 1 | B-Post Grossbrief | CHF 3.00 |
| 2–4 | PostPac Economy | CHF 8.00 |

### Appearance per shop

Settings → «Storefront»:

- **Colours:** main colour, accent and light accent (colour pickers). Shades are derived automatically. A live preview shows the result, with a contrast check (WCAG: 4.5 : 1 for normal text, 3 : 1 for large text).
- **Hero visual:** your own image (resized to max. 1600 px), the heart drawn from text, or nothing.
- **Branding:** optional logo instead of the shop name, and an optional favicon.
- **Sections:** books, «So funktioniert’s» and «Schon bestellt?» can be switched on and off.
- **Link preview:** an image. Without one, the hero image or the first book cover is used. Title and description are texts (below).

### Languages

- The platform offers German, French, Italian and English (`App\Enums\Locale`). Each shop chooses its languages and a default language (`tenants.settings.locales`, `settings.default_locale`; wizard step «Shop» and settings tab «Shop»). Existing shops were set to German + English, German first, by migration. Removing a language keeps its texts.
- The storefront has no URL prefixes. A visitor's language is the saved choice (cookie `lang`, one year) if the shop offers it, otherwise the shop's default. There is no browser detection, so crawlers and link previews always get the default. Falling back doesn't change the cookie (on the platform domain it is shared by all shops).
- Language links: `?lang=xx` on any storefront URL saves the choice and redirects (302) to the same URL without it. Links in emails carry the order's language.
- Switcher in the header: hidden with one language, two buttons with two, a menu with full names with three or more.
- Orders store the customer's language (only the shop's languages are accepted). Emails, invoice, cover letter and Stripe Checkout use it; if the shop no longer offers it, they use the default.

### Editable texts

Settings → «Texte» (admins only): every customer-facing text, one area at a time (start page, order form, order page, cookie banner, labels, each email, cover letter, invoice), one tab per language of the shop, default language first. In the other tabs, every field shows the current text in the default language as a reference.

- Catalogue: `App\Support\Texts\TextCatalog` (label, kind, placeholders per text). Defaults stay in `backend/lang/{de,fr,it,en}/{storefront,mail,letter,invoice}.php` (German is the source of truth; French and Italian are translated from it, informal «tu», same keys and placeholders) under the same key; overrides are stored in `tenants.settings.texts.<locale>.<key>`. Empty or unchanged fields use the default.
- Placeholders are written `{name}`; `{shop}` (shop name) works everywhere, the others are listed per text. Unknown placeholders are rejected on save. Values are always escaped.
- Longer running texts are Markdown (bold, italic, link, list; print texts bold and italic only), rendered safely on the server. Short texts (titles, subjects, buttons, labels) are plain text.
- Each Aktion can add optional extra texts («Zusatztexte») at twelve places: start page, order form, order page, seven emails (confirmation, payment received, reminder, moved, signed, shipped, abandoned) and as a P.S. in the cover letter and a note on the invoice. Stored in `aktionen.texts.<locale>.<name>`, empty for new Aktionen; one tab per language of the shop.
- Not editable: the refund email, form validation messages, Stripe line items (except «Versand», which uses the invoice text), the admin, internal print outputs (stack list, labels) and the platform start page.
- The storefront gets all texts in the shop's languages from `GET /api/tenants/{slug}` (`tenant.texts`, plus `tenant.locales` and `tenant.default_locale`) and reads them with `$text` / `$textHtml` (`frontend/app/plugins/texts.ts`). The i18n files only keep the platform picker, validation messages and fallbacks for pages without a shop.

### Copy & tone

- Swiss Standard German with "du": approachable, warm, a little cute, very few emojis. German is the source of the default texts; English, French and Italian follow it. Customer-facing texts are editable per shop (see «Editable texts»), with defaults in the Laravel lang files.
- Swiss conventions: "ss" instead of "ß", `CHF 12.50`, `15.10.2026`, `14:30`.

### Consent & tracking

- vanilla-cookieconsent with categories (`necessary`, `analytics`, `marketing`).
- A provider-agnostic `useTracking()` composable in Nuxt. Providers (Umami first; Matomo/GA optional) are registered per category and receive events only after consent.
- Conversion events: `aktion_viewed`, `order_started`, `email_entered`, `step_completed`, `order_submitted`, `payment_completed`.

## 6. Payments

| Method | Flow | Gift allowed |
|---|---|---|
| Online (Stripe: card, TWINT) | Stripe Checkout; webhook `checkout.session.completed` → paid | yes |
| Invoice by email (pay in advance) | QR-bill PDF by email; marked paid manually in admin; reminder before deadline | yes |
| Invoice in parcel | Order confirmed immediately; QR bill printed with the cover letter | no |

- Each method can be switched on or off per tenant.
- QR bills via `sprain/swiss-qr-bill`. Creditor reference (SCOR) with a normal IBAN, or QRR with a QR-IBAN, depending on tenant settings.
- No automatic bank import (ZKB private account). The admin has a fast "mark as paid" action with a search by reference.
- Refunds: Stripe refunds (full/partial) from the admin; bank refunds are recorded manually.
- Stripe webhook: `admin.widmungen.rose-water.ch/webhooks/stripe/{tenant}`, signature verified with the tenant's webhook secret.
- No VAT (seller not registered). The invoice template allows a VAT line in case another tenant needs it.

## 7. Admin (Filament)

### Roles (per tenant)

- **Super admin** (platform): manage tenants.
- **Admin**: everything within the tenant, including settings.
- **Fulfillment**: orders, exports, shipping; no settings.
- **Widmen**: the signing workflow (and moderation, if granted).

### Resources & pages

- Books, Aktionen, Orders (filters by Aktion, status, payment; bulk actions), Users.
- **Settings page** per tenant: everything in TenantSettings.
- **Moderation queue**: all dedication wishes of an Aktion → approve / edit / flag with a query («Rückfrage»). «Widmungsaktion vorbereiten» requires every wish to be checked (approved or flagged).
  - **Einzeln prüfen**: one wish at a time, keyboard-driven like the Widmen page: `Enter` = approve, `A` = adjust, `F` = query, `←`/`→` = back/skip. The list is fixed when the page opens.
- **Widmen** (see §8).
- **Exports per Aktion**:
  - cover letters (with QR bill where needed) as one merged PDF
  - address labels / envelopes
  - **Stapelliste** (for preparing and packing, not for signing): books to prepare per title, then per order the post-it, order number, name, books per title, Brief/Paket, Geschenk and open queries, with boxes «Vorbereitet» and «Verpackt». Orders with the invoice in the parcel are highlighted («Rechnung beilegen»). The book counts also appear on «Vorbereiten & Versand», before preparing and in the overview.
  - **Versand table** on «Vorbereiten & Versand»: the stack in post-it order (books, Brief/Paket, invoice, status), filtered to «gewidmet, nicht versendet». Ship one order (with tracking number) or select all and untick the exceptions; only signed orders can be selected. The Aktion moves to «Versand» once something has shipped and to «Abgeschlossen» once nothing is open anymore.
  - **Offene Bestellungen** on «Vorbereiten & Versand» (after the deadline): every order that isn't shipped or cancelled, with the reason (unbezahlt · bezahlt, noch kein Post-it · Rückfrage offen · noch nicht fertig gewidmet · gewidmet, nicht versendet). Per order: «Auf den Stapel» (next post-it number), mark paid, move, refund, cancel; in bulk: move, cancel. Before preparing it only lists unpaid orders, so late transfers can be recorded first. The dashboard shows how many orders wait for a decision.
  - order and revenue report as CSV/XLSX
- **Dashboard**: orders per Aktion, open payments, revenue per book, conversion funnel (drafts → submitted → paid).
- Order actions: mark paid, refund, move to Aktion, cancel, mark shipped (bulk, with date and optional tracking number), resend email, timeline view.

### Print settings

- Label sheets: presets plus a custom grid (page size, columns, rows, margins, gaps), and a **start position** so partly used sheets can be reused. Presets (dimensions to be verified): Avery Zweckform 3475 (70×36 mm, 3×8), 3424 (105×48 mm, 2×6).
- Envelope mode: `labels` (default) | `window_right` | `window_left` (C5/C4 window envelopes; the cover letter's address block is placed in the window position) | `direct_print` (C5/C4).

## 8. Widmen workflow

The goal is focus and safety without extra paper.

1. After the order deadline, Admin or Fulfillment presses **«Widmungsaktion vorbereiten»** on «Vorbereiten & Versand» (once per Aktion; test Aktionen any time). The page first shows a checklist (deadline, unchecked wishes, queries, unpaid orders) and the books to prepare per title. Every confirmed order gets a **Stapelnummer** 1…n, sorted by book and then by order date, and the Aktion stops taking orders. Only then do the overview, Stapelliste, letters, labels and shipping appear.
   - Admins can undo it as long as no book has been signed (the numbers are removed).
   - Orders confirmed later (e.g. a late bank transfer) are added one by one with the next number («Auf den Stapel» in «Offene Bestellungen»).
   - Orders with an open query get the last numbers, so they can't get lost and lie at the end of the stack; nothing has to be put aside. An order is always handled as a whole: if one of its books has a query, none of its books is signed until it is resolved.
2. The team prepares the stack as before: one post-it with the Stapelnummer per order, the order's books side by side, the next order turned 90°.
3. The Widmen page (a full-screen toggle using the browser fullscreen API, with a stripped-down layout) shows exactly one copy at a time:
   - large Stapelnummer and "Buch 2 von 3"
   - book title
   - recipient name and dedication wish in big type
   - progress ("17 von 63")
4. Keyboard: `Space` = gewidmet → next, `U` = undo last, `F` = flag (the whole order is put aside; doesn't block the session), `P` = pause.
   - Orders with a query are skipped as a whole. Resolved before the signing reaches the order: signed in its place. A query raised after preparing (in the moderation queue, or `F` while signing): the screen stops before that post-it and asks to put it aside with all its books; once resolved, the order comes up at the end as «Nachgeholt», with a checkpoint for its post-it.
   - The end screen lists orders with open queries by Stapelnummer (at the end of the stack or put aside) and can continue once they are resolved.
5. **Checkpoints**: every N copies (default 5, configurable), and always after skip/undo/pause, the screen asks "Welche Nummer steht auf dem Post-it vor dir?" Anna types the number.
   - Match → continue.
   - Mismatch → stop screen with the expected order and nearby numbers, so the gap is easy to find.
6. Sessions survive reloads and can be resumed. Every action is logged.
7. When all copies of an order are signed, the order becomes `signed`.

A barcode scanner mode is a possible later addition, not in scope.

## 9. Emails (Mailgun, queued, per-tenant sender)

- order confirmation
- invoice (pay in advance)
- payment received
- payment reminder
- abandoned order reminder (once, after 24 h, with opt-out)
- moved to the next Aktion
- gewidmet (only if the shop enables it in the settings; off by default, sent 30 minutes after signing so an undo doesn't leave a wrong mail)
- shipped
- cancelled
- refunded

Every email links to the tracking page.

## 10. Integrations

- **Newsletter**: on opt-in, Laravel sends a queued, HMAC-signed webhook to a per-tenant URL (n8n → Notifuse).
- **PDFs**: Blade templates rendered by **Gotenberg** (Chromium), merged by Gotenberg.
- **Monitoring**: Laravel Horizon (queues). Error tracking and business monitoring are on the backlog (issues #4, #5).

## 11. Privacy & retention

- Drafts are deleted after 30 days. The abandoned reminder is sent at most once.
- Customer data is kept as long as accounting requires (10 years for invoice data).
- Dedication texts (recipient name + wish entered per copy) are not needed for accounting. Default: anonymised 12 months after shipping (configurable per tenant, 0 = keep).
- Fonts are self-hosted, and there are no third-party requests before consent.

## 12. Tech stack & architecture

- **Backend**: Laravel (latest stable), Filament (latest stable), PostgreSQL, Redis, Horizon, spatie/laravel-permission (teams = tenants).
- **Frontend**: Nuxt 4, Tailwind 4, @nuxtjs/i18n.
- **No CORS**: on storefront hosts the web server / reverse proxy routes `/api/*` straight to Laravel and everything else to Nuxt, so the browser only ever talks to its own origin. During SSR, Nuxt calls Laravel directly (`NUXT_API_INTERNAL_URL`). The tenant is resolved from the host (passed explicitly) or the path slug.
- **Design**:
  - colours `#2B3C69` (navy), `#D989B2` (pink), `#EEC6D8` (light pink), white
  - fonts: Barlow (headings), Hanken Grotesk (body), self-hosted
  - small radius (~2–4 px), lots of whitespace
  - underlined arrow links and curved-text motifs, as on the landing page for «Herz» (brand assets are not stored in this repository)
- **Repo layout**:
  ```
  backend/    Laravel + Filament
  frontend/   Nuxt
  docker/     production Dockerfiles + compose
  .ddev/      local development
  docs/       spec, deployment guide
  ```

### Production (VPS, behind existing reverse proxy)

docker-compose services:

- `app`: serversideup/php fpm-nginx (web + API + webhooks)
- `horizon` and `scheduler`: same image, different command
- `frontend`: Node, Nuxt SSR
- `postgres`, `redis`, `gotenberg`
- `photon`: geocoder for address autocomplete (OSM data for CH + LI)

Reverse proxy routing: `admin.*` → app; storefront hosts → `/api/*` to app, everything else to frontend.

### Development (DDEV)

- `web` container: PHP for Laravel, plus Nuxt dev server as `web_extra_daemons`. nginx routes by hostname:
  - `admin.widmungen.ddev.site` → Laravel
  - `widmungen.ddev.site` and `*.ddev.site` test tenant domains → Nuxt
- Postgres as the DDEV database, Redis add-on, Gotenberg and Photon as extra compose services, Mailpit built in.
- Stripe webhooks locally through `stripe listen --forward-to`.

## 13. Phases

1. **Foundation**: repo layout, DDEV, Laravel + Filament with tenancy and roles, tenant wizard + settings, Books, Aktionen, Nuxt storefront skeleton with design system, i18n, consent, multi-step order form with drafts, tracking page.
2. **Payments**: Stripe Checkout + webhook + refunds, QR-bill invoices, pay-in-advance flow and reminders, rolling unpaid orders over to the next Aktion.
3. **Operations**: moderation queue, Widmen workflow, Gotenberg PDFs (letters, invoices, labels, packing list), shipping, all status emails.
4. **Extras & release**: newsletter webhook, abandoned-order reminder, dashboard and reports, tracking events, deployment docs, and:
   - production Docker stack with an internal web server container (nginx/Caddy) that routes `/api/*` and `/storage/*` to Laravel and everything else to Nuxt, so the outer reverse proxy only forwards hosts
   - verify trusted proxy ranges / client IP forwarding in production
   - CI pipeline (from the first release candidate on) that runs the tests and builds production-ready Docker images on every push

## 14. Open points

- Exact label sheet in use (presets are configurable, so this is not a blocker).
- Terms/privacy texts (legal content is provided by the tenant).
