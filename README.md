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

### Gotchas

- On macOS, DDEV syncs files into the container with Mutagen. If a change doesn't show up, run `ddev mutagen sync`.
- Frontend dependencies are installed inside the container (`ddev exec -d /var/www/html/frontend pnpm add …`), because some packages ship platform-specific binaries.
- If the storefront shows a "Could not load …/node_modules/.pnpm/…" error after dependencies changed, clear Nuxt's cache and restart it: `ddev exec "rm -rf /var/www/html/frontend/.nuxt && supervisorctl restart webextradaemons:nuxt"` (or `ddev restart`).
