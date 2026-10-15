# Deployment

Production runs as a Docker Compose stack on a VPS behind CloudPanel's nginx. Images are built by GitHub Actions whenever a release is published and pulled from the GitHub Container Registry (GHCR).

```
Internet ──https──▶ CloudPanel nginx (TLS) ──http──▶ 127.0.0.1:8080
                                                      │
                                              web (internal nginx)
                       admin domain ─────────────────┤──▶ app (Laravel, Filament)
                       storefront /api, /storage ────┤──▶ app
                       storefront everything else ───┘──▶ frontend (Nuxt)

app · horizon (queue) · scheduler · frontend · postgres · redis · gotenberg · backup
```

## 1. Releasing a version

1. Merge everything into `main`.
2. On GitHub: **Releases → Draft a new release**, tag e.g. `v1.0.0`, publish.
3. The **Release** workflow runs the backend tests, checks the frontend build and pushes three images:
   - `ghcr.io/nr-anna-rosenwasser/widmungsaktionen-backend`
   - `ghcr.io/nr-anna-rosenwasser/widmungsaktionen-frontend`
   - `ghcr.io/nr-anna-rosenwasser/widmungsaktionen-backup`

   tagged `1.0.0`, `1.0` and `latest` (pre-releases don't get `latest`).

The workflow can also be started by hand (Actions → Release → Run workflow); those images are only tagged with the commit hash.

## 2. First installation on the VPS

Requirements: Docker Engine with the Compose plugin.

```sh
# 1. Folder with the stack files (only the docker/ folder is needed)
mkdir -p /opt/widmungen && cd /opt/widmungen
# copy docker/compose.yml, docker/.env.example and docker/web/ from the repo here

# 2. Allow the server to pull the private images (one-time).
#    Create a GitHub token (classic) with only the "read:packages" scope.
echo "<TOKEN>" | docker login ghcr.io -u <github-username> --password-stdin

# 3. Settings
cp .env.example .env
nano .env        # domains, database password, Mailgun SMTP, APP_VERSION
docker compose pull
docker compose run --rm app php artisan key:generate --show   # paste the output into APP_KEY in .env

# 4. Start. Migrations run automatically when the app container starts.
docker compose up -d
docker compose ps

# 5. First super admin (prompts for name and password)
docker compose exec app php artisan users:create-super-admin
```

Then log in at `https://admin.widmungen.rose-water.ch` and create the shop with **Neuer Shop**: seller data, IBAN, Stripe keys, shipping, first admin.

> Don't run `db:seed` in production: the seeder creates demo accounts with the password `password`.

## 3. CloudPanel

Create a **Reverse Proxy** site for each domain, all pointing to `http://127.0.0.1:8080` (or the `HTTP_PORT` from `.env`):

| Domain | Purpose |
|---|---|
| `admin.widmungen.rose-water.ch` | Admin panel, Stripe webhooks |
| `widmungen.rose-water.ch` | Storefront (platform domain) |
| a shop's own domain | Storefront of that shop (also add it in the shop settings under "Eigene Domains") |

Issue a Let's Encrypt certificate for each site in CloudPanel. CloudPanel's default reverse-proxy vhost passes `Host`, `X-Forwarded-For` and `X-Forwarded-Proto`, which the app relies on:

- `X-Forwarded-Proto: https` so links and cookies use https,
- `X-Forwarded-For` so rate limits apply per visitor, not to everyone at once.

To verify after setup: open the admin panel. If you get redirected in a loop or links start with `http://`, the proto header is missing.

## 4. Updates

```sh
cd /opt/widmungen
# set APP_VERSION in .env to the new release (or keep "latest")
docker compose pull
docker compose up -d
```

The app container runs the database migrations on start. The internal router finds recreated containers by name, so no restart of `web` is needed.

Rolling back: set `APP_VERSION` to the previous release and run the same commands. Migrations are not rolled back automatically. A release with breaking database changes says so in its notes.

## 5. Mail (Mailgun SMTP)

In `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.eu.mailgun.org   # EU region; US region: smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@mg.your-domain.ch
MAIL_PASSWORD=<SMTP password from Mailgun>
MAIL_FROM_ADDRESS=widmungen@rose-water.ch
```

Each shop can set its own sender address in its settings. That domain must be verified in Mailgun (SPF/DKIM), otherwise mails end up in spam or are rejected.

## 6. Stripe

In the Stripe dashboard (live mode) add a webhook endpoint with the URL shown in the shop settings, e.g. `https://admin.widmungen.rose-water.ch/api/webhooks/stripe/anna-rosenwasser`, with these events:

`checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`, `charge.refunded`

Copy the signing secret (`whsec_…`) into the shop settings. Payment methods (cards, TWINT, …) are switched on in the Stripe dashboard.

## 7. Backups

The `backup` container makes a nightly backup (default 02:30 Swiss time) into `BACKUP_PATH` (default `./backups`):

- `daily/db_<date>.dump`: database (Postgres custom format), kept 14 days
- `daily/uploads_<date>.tar.gz`: uploaded files (book covers)
- `weekly/`: Sunday copies, kept 8 weeks

Run a backup now: `docker compose exec backup backup`

**Off-site copy (recommended):** backups on the same server don't help if the server is lost. Configure an rclone remote (e.g. S3-compatible storage at a Swiss provider) in `rclone/rclone.conf` next to `compose.yml`, then set `RCLONE_REMOTE=<remote>:<bucket-or-folder>` in `.env` and `docker compose up -d backup`. After each nightly backup, the backup folder is synced there.

**Restore:**

```sh
docker compose stop app horizon scheduler
docker compose exec -T postgres dropdb -U widmungen widmungen
docker compose exec -T postgres createdb -U widmungen widmungen
docker compose exec -T postgres pg_restore -U widmungen -d widmungen --no-owner < backups/daily/db_<date>.dump
# uploads:
docker compose run --rm -v "$PWD/backups:/backups" --entrypoint sh app -c "tar -xzf /backups/daily/uploads_<date>.tar.gz -C /var/www/html/storage/app/public"
docker compose start app horizon scheduler
```

Test a restore every now and then. A backup that has never been restored is only a hope.

## 8. Useful commands

```sh
docker compose logs -f app          # Laravel / admin
docker compose logs -f horizon      # queue: mails, PDFs, webhooks
docker compose exec app php artisan horizon:status
docker compose exec app php artisan aktionen:sync-status
docker compose exec app php artisan tinker
```

Horizon's dashboard is at `https://admin.widmungen.rose-water.ch/horizon` (super admins only).
