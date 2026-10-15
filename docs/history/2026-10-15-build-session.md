---
# Machine-readable summary of the build session on 2026-10-15.
# The Markdown below repeats it in prose. Keep both in sync if you edit.
session:
  date: 2026-10-15
  participants: [Timothy Oesch (product owner, developer), Claude (implementation)]
  outcome: MVP built in four phases, deployed to the VPS, pre-release v0.1.0 published
  repository: https://github.com/NR-Anna-Rosenwasser/widmungsaktionen
  release: v0.1.0 (pre-release; images ghcr.io/nr-anna-rosenwasser/widmungsaktionen-{backend,frontend,backup}:0.1.0)

product:
  purpose: >-
    Recurring "Widmungsaktionen": readers order signed, personally dedicated
    books; the author signs all orders of one round in a single session.
    Always an open or upcoming round, so nobody has to wait.
  first_tenant: Anna Rosenwasser (author, member of the Swiss National Council)
  later_option: other sellers, e.g. a publisher with several authors (multi-tenancy)

stack:
  backend: Laravel 13, Filament 5, PostgreSQL 17, Redis, Horizon
  frontend: Nuxt 4, Tailwind 4, @nuxtjs/i18n (de-CH with "du", en)
  pdf: Gotenberg (Chromium), sprain/swiss-qr-bill, endroid/qr-code
  payments: Stripe Checkout (hosted page), Swiss QR bills
  mail: Mailgun via SMTP
  dev: DDEV (macOS + OrbStack, Mutagen sync)
  prod: Docker Compose on a VPS behind CloudPanel nginx; images on GHCR

decisions:
  - id: tenancy
    decision: Tenant = seller; Filament tenancy; storefront per tenant via own domain or /<slug> on the platform domain
    why: Lets the tool be offered to Rotpunktverlag later without a rewrite
  - id: platform-root
    decision: Bare platform domain redirects to the only tenant, otherwise shows a seller picker
  - id: one-open-aktion
    decision: At most one open Aktion per tenant (form rule + partial unique index); scheduler opens/closes Aktionen
  - id: roles
    decision: Role per tenant on the tenant_user pivot (Admin, Fulfillment, Widmen); no spatie/permission
    why: Fixed small role set, simpler than a permission package
  - id: api-routing
    decision: Web server routes /api and /storage on storefront hosts straight to Laravel; Nuxt calls Laravel directly during SSR
    why: Same origin (no CORS) without a Node proxy; the Node proxy corrupted bodies with umlauts and hop-by-hop headers
  - id: internal-router
    decision: Keep a small nginx ("web") inside the Compose stack; CloudPanel forwards every domain to one port
    why: Routing rules versioned in the repo; any new domain is just "reverse proxy to port 8080"
  - id: payment-methods
    decision: Online (Stripe), invoice by email (pay in advance), invoice in parcel (not for gifts)
  - id: unpaid-after-deadline
    decision: Reminder before the deadline; when the Aktion closes, unpaid orders move to the next Aktion once, then get cancelled (configurable)
  - id: gifts
    decision: One delivery address per order; gift message required; sender always named on the cover letter
  - id: max-copies
    decision: Max 4 books per order; larger orders by email
  - id: shipping-prices
    decision: CHF 3 letter (1 book), CHF 8 parcel (2-4), configurable per tenant
  - id: signing-workflow
    decision: Post-it numbers per Aktion; Widmen mode shows one copy at a time; checkpoint asks Anna to TYPE the post-it number (not click "yes")
    why: Avoids autopilot clicking; no extra paper slips; barcode scanner deferred
  - id: moderation
    decision: Every recipient name and wish is reviewed before signing; plain signatures skip review
  - id: signed-mail-delay
    decision: 'The "gewidmet" mail is sent 30 minutes after signing and skipped if undone'
  - id: online-confirmation
    decision: Online orders get one confirmation after payment instead of two mails
  - id: timezone
    decision: App timezone Europe/Zurich
  - id: retention
    decision: Drafts deleted after 30 days; dedication texts anonymised 12 months after shipping (configurable)
  - id: newsletter
    decision: Opt-in sent as HMAC-signed JSON to a per-tenant webhook (n8n -> Notifuse), not to Notifuse directly
    why: Newsletter tool may change
  - id: mail-transport
    decision: Mailgun SMTP (not the Mailgun API package)
  - id: stripe-ui
    decision: Keep Stripe's hosted Checkout page; in-page Payment Element is backlog issue #2
  - id: fonts
    decision: Barlow (headings), Hanken Grotesk (body), self-hosted; OFL replacements for DIN Pro / HAL Four Grotesk
  - id: releases
    decision: Publishing a GitHub release runs tests and pushes images to GHCR (version, major.minor, latest only for full releases)
  - id: backups
    decision: Nightly pg_dump + uploads archive in the stack (14 daily, 8 weekly), optional rclone off-site copy
  - id: error-tracking
    decision: Deferred to after the MVP (issue #4); business monitoring is issue #5

conventions:
  branching: main is always deployable; one branch per phase/feature, merged via PR; small CI/doc fixes may go straight to main
  commits: 'End commit messages with "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"'
  tests: 'Run "ddev artisan test" (Postgres database "testing"); tests must never call outside services (PDF renderer is faked, stray HTTP blocked)'
  money: Stored in Rappen (integers)
  language: Admin and customer copy in Swiss Standard German with "du" (no ß); English translations alongside

history:
  - {phase: 1, pr: null, summary: "DDEV, Laravel+Filament with tenancy and roles, books, Aktionen, storefront API, Nuxt storefront, order form with drafts, tracking page, i18n, consent"}
  - {phase: 2, pr: 1, summary: "Team management, payments and references, Stripe Checkout and webhooks, admin payment actions, QR invoices, order emails, reminders, unpaid handling, undo for payments and cancellations"}
  - {phase: 3, pr: 3, summary: "Moderation, signing stack and Widmen mode, printing (stack list, cover letters, labels, envelopes), shipping, CSV export, demo orders seeder"}
  - {phase: 4, pr: 6, summary: "Newsletter webhook, abandoned-order reminder, clean-up, dashboard, production Docker stack, backups, GHCR release workflow, deployment guide"}
  - {phase: release, pr: null, summary: "v0.1.0 pre-release; deployed to the VPS; Stripe test webhooks set up and working"}

open_items:
  - Label sheet dimensions not yet verified on paper
  - GHCR packages are private while the repo is private; make them public after the repo goes public
  - Old PR refs (#1, #3) on GitHub still contain the removed docs/brand images (owner decided this is acceptable)
  - Live Stripe setup (live keys and endpoint) before real orders
  - 'Backlog issues: #2 in-page payment, #4 error tracking, #5 business monitoring'

gotchas:
  - "DDEV on macOS syncs via Mutagen: run `ddev mutagen sync` if a change does not show up"
  - "Nuxt dev server may hold stale paths after dependency changes: clear .nuxt and restart the nuxt daemon"
  - "Storefront translation changes may need a Nuxt restart"
  - "The production image needs the PHP gd extension (QR bill logo), which Composer does not declare"
  - "nginx must resolve container names at request time, otherwise 502 after containers are recreated"
  - "Empty folders are not in git: do not reference them in phpunit.xml"
---

# Build session, 15 October 2026

## Outcome

In one day the app went from an empty repository to a deployed MVP. It was built in four phases, each reviewed as a pull request. The pre-release **v0.1.0** is published, its images are on GHCR, the stack runs on the VPS, and Stripe test payments work end to end.

## Starting point

Anna Rosenwasser runs regular signing rounds ("Widmungsaktionen") for her books. Until now each round needed its own form, and between rounds people had to be told to wait. The goal was a permanent storefront where:

- readers can always order for the current or next round, see the expected delivery and follow their order through a personal link
- payment works online (Stripe) or by Swiss QR invoice
- the team manages orders, payments, moderation, signing, printing and shipping in one backend

## Key decisions and why

**Architecture**

- **Multi-tenancy from day one:** the seller is the tenant, so the tool could later serve a publisher with several authors. Each shop gets its own domain, or `/<slug>` on the platform domain. The bare platform domain redirects when there is one shop and shows a picker when there are several.
- **Routing:** the browser only talks to its own origin, and the web server sends `/api` and `/storage` straight to Laravel. A Node proxy in Nuxt was tried first and dropped: it corrupted bodies with umlauts and mishandled certain headers.
- **Internal router in production:** a small nginx inside the Compose stack does the routing. CloudPanel forwards every domain to one port, and the routing rules stay versioned in the repo.
- **Roles:** stored per tenant on the link between user and shop (Admin, Fulfillment, Widmen). The role set is small and fixed, so no permission package.

**Ordering and payment**

- **Order limits:** max 4 books per order, larger orders by email.
- **Gifts:** one delivery address per order and a required gift message. The sender is always named on the cover letter.
- **Shipping:** CHF 3 for a letter (1 book), CHF 8 for a parcel (2–4 books).
- **Payment methods:** Stripe Checkout (Stripe's own page), QR invoice by email (pay in advance), or invoice in the parcel (not for gifts).
- **Unpaid orders:** a reminder goes out before the deadline. When the round closes, unpaid orders move to the next round once, then get cancelled.
- **Mistakes can be undone:** a manually recorded payment can be reset, and a cancellation can be reversed.

**Signing**

- **Post-its instead of paper slips:** orders get per-round post-it numbers.
- **Widmen mode:** shows one copy at a time in full screen. A regular checkpoint asks Anna to **type** the post-it number in front of her rather than click "yes", which avoids autopilot clicking (she has ADHD).
- **Moderation first:** all names and wishes are reviewed before signing; plain signatures skip review.
- **Delayed mail:** the "gewidmet" email goes out 30 minutes later and is skipped if the signing was undone.

**Communication and data**

- **Emails:** sent from the shop's own sender address, in the customer's language and in the brand colours. Mailgun via SMTP.
- **Newsletter:** opt-ins go as a signed webhook to n8n, which forwards to Notifuse, so the newsletter tool can change later.
- **Data retention:** drafts are deleted after 30 days, and dedication texts are anonymised 12 months after shipping.
- **Timezone:** Europe/Zurich.

**Operations**

- **Releases:** publishing a GitHub release runs the tests and pushes three images to GHCR.
- **Backups:** nightly, with an optional off-site copy.
- **Deferred:** error tracking (#4), business monitoring (#5) and in-page payment (#2) are on the backlog until after the MVP.

## What was built

| Phase | Pull request | Contents |
|---|---|---|
| 1 | committed to `main` | DDEV setup, Laravel + Filament with tenancy and roles, books, Aktionen, storefront API, Nuxt storefront with multi-step order form, drafts, gifts, tracking page, i18n, consent |
| 2 | #1 | Team management, payments with QR references, Stripe Checkout and webhooks, admin payment actions, QR invoices via Gotenberg, order emails, reminders, unpaid handling, undo |
| 3 | #3 | Moderation, signing stack and Widmen mode with post-it checks, print documents (stack list, cover letters, labels, envelopes), shipping, CSV export, demo orders seeder |
| 4 | #6 | Newsletter webhook, abandoned-order reminder, data clean-up, dashboard, production Docker stack, backups, release workflow, deployment guide |
| Release | – | v0.1.0 pre-release, deployed to the VPS, Stripe test webhooks set up |

Where to find things:

- [docs/SPEC.md](../SPEC.md): specification
- [docs/DEPLOYMENT.md](../DEPLOYMENT.md): server setup, updates, Stripe, backups and restore
- [README.md](../../README.md): local development

## Lessons learned

- **Test like production.** Running the production stack locally caught a missing PHP extension (`gd`, needed for the QR bill) and 502 errors after container updates. Neither showed up in DDEV.
- **Tests must not depend on outside services.** They passed locally only because DDEV runs Gotenberg, and failed in CI. A fake PDF renderer and blocked HTTP requests now prevent that.
- **Git doesn't track empty folders,** so don't reference them in configuration files.
- **With Mutagen sync, check that a change actually reached the container** before drawing conclusions from a test.

## Open items

- Verify label sheet dimensions on paper.
- When the repository goes public, also make the three GHCR packages public.
- The `docs/brand` images were removed from the branches' history. They remain in GitHub's references for the old PRs #1 and #3, which the owner accepted.
- Set up live Stripe (live keys and live webhook endpoint) before taking real orders.
- Backlog: #2 in-page payment, #4 error tracking, #5 business monitoring.
