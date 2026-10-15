# Widmungen

Order signed, personally dedicated books during recurring signing rounds ("Widmungsaktionen").

- `backend/`: Laravel + Filament (admin panel, storefront API)
- `frontend/`: Nuxt (public storefront)
- `docs/SPEC.md`: specification and phases
- `.ddev/`: local development environment

## Local development

Requirements: [DDEV](https://ddev.com) (≥ 1.25) with a Docker provider (e.g. OrbStack).

```sh
ddev start
ddev composer install
ddev artisan key:generate   # first time only
ddev artisan migrate:fresh --seed
ddev artisan storage:link
```

Locally, the seeder also creates about 40 demo orders for the «Herbst-Widmungsaktion 2026» in every payment method and status before signing day (paid, unpaid, invoice in parcel, reminded, refunded, cancelled, abandoned drafts), some with gifts and dedication wishes still to be moderated. No emails are sent. To add them again: `ddev artisan db:seed --class=DemoOrdersSeeder`.

`ddev start` also runs the Nuxt dev server, Horizon (queues) and the scheduler as background processes inside the web container.

| URL | What |
|---|---|
| https://widmungen.ddev.site | Storefront (platform domain; redirects to the only shop) |
| https://anna-test.ddev.site | Storefront via a custom domain (seeded for Anna's shop) |
| https://admin.widmungen.ddev.site | Admin panel |
| https://admin.widmungen.ddev.site/horizon | Queue dashboard (super admins) |
| `ddev launch -m` | Mailpit (all outgoing mail) |

Seeded logins (password `password`):

- `admin@widmungen.test`: super admin (all shops, can create shops)
- `anna@widmungen.test`: role "Widmen" in Anna's shop

### How requests flow

On storefront hosts, nginx sends `/api/*` straight to Laravel and everything else to Nuxt, so the browser only ever talks to its own origin (no CORS). During server-side rendering, Nuxt calls Laravel directly via `NUXT_API_INTERNAL_URL`. Production uses the same split in the reverse proxy.

Tenants are resolved from the host (`tenant_domains`) or from the first path segment on the platform domain (`/anna-rosenwasser/...`).

### Tests

```sh
ddev artisan test
```

Tests run against a separate Postgres database (`testing`), created automatically on `ddev start`.

### Payments and emails locally

- All outgoing mail lands in Mailpit (`ddev launch -m`). Mails are queued; Horizon processes them.
- Invoices (QR bills) are rendered by the Gotenberg container.
- To test Stripe, put your **test** keys into the shop settings (Einstellungen → Zahlung & E-Mail), then forward webhooks with the [Stripe CLI](https://docs.stripe.com/stripe-cli):

  ```sh
  stripe listen --forward-to https://admin.widmungen.ddev.site/api/webhooks/stripe/anna-rosenwasser
  ```

  Copy the `whsec_…` secret it prints into the shop's "Webhook signing secret". In production, create the webhook endpoint in the Stripe dashboard with the URL shown in the shop settings.
- Scheduled jobs (opening/closing Aktionen, payment reminders) run via `schedule:work`. Trigger them by hand with `ddev artisan aktionen:sync-status` or `ddev artisan orders:send-payment-reminders`.

### Gotchas

- On macOS, DDEV syncs files into the container with Mutagen. If a change doesn't show up, run `ddev mutagen sync`.
- Frontend dependencies are installed inside the container (`ddev exec -d /var/www/html/frontend pnpm add …`), because some packages ship platform-specific binaries.
- If the storefront shows a "Could not load …/node_modules/.pnpm/…" error after dependencies changed, clear Nuxt's cache and restart it: `ddev exec "rm -rf /var/www/html/frontend/.nuxt && supervisorctl restart webextradaemons:nuxt"` (or `ddev restart`).
- Changes to the storefront translation files (`frontend/i18n/locales/*.json`) may need a Nuxt restart to show up: `ddev exec "supervisorctl restart webextradaemons:nuxt"`.
