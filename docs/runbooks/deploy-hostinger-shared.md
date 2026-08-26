# Runbook — Deploying piotrack to Hostinger shared hosting

A demo/staging deploy you can click through. Follow top to bottom; every command
is meant to be copy-pasted over SSH.

> **Before anything else: rotate the SSH password.** If it has ever been pasted into a chat,
> an email or a ticket, treat it as public. hPanel → Advanced → SSH Access → change password, and
> prefer adding an SSH key and disabling password auth.

## What this host can and cannot do

|              | Shared hosting                                                       |
| ------------ | -------------------------------------------------------------------- |
| Database     | **MySQL 8** — validated in CI (`tests-mysql` job)                    |
| PHP          | 8.2+ selectable in hPanel; 8.3/8.4 preferred                         |
| Composer     | Usually available; if not, upload `vendor/`                          |
| Node / Vite  | Usually **absent** — build assets locally and upload `public/build`  |
| Redis        | **Absent** — cache, session and queue use the `database` driver      |
| Queue worker | No long-running processes — cron runs `queue:work --stop-when-empty` |
| Scheduler    | One cron entry for `schedule:run`                                    |

Because there is no Redis and no daemon, this is a **demo/staging** target. A production
deployment wants a VPS or Laravel Cloud.

---

## 1. Prepare the database (hPanel)

hPanel → **Databases → MySQL Databases**. Create a database and user, grant all privileges, and
note: database name, username, password, and host (usually `localhost`).

## 2. Build assets locally and commit them

The server has no Node, so the compiled front-end must travel with the code. From your machine:

```bash
npm ci && npm run build
```

`public/build` is gitignored by default. For this host, commit it on a deploy branch:

```bash
git checkout -b deploy/hostinger
git add -f public/build
git commit -m "Build assets for shared-hosting deploy"
git push -u origin deploy/hostinger
```

Re-run these four commands whenever front-end code changes.

## 3. Connect and clone

```bash
ssh -p 65002 u120417915@156.67.75.160
```

```bash
cd ~
git clone https://github.com/Dalbeirdev/piotrack.git app
cd app && git checkout deploy/hostinger
```

## 4. Install PHP dependencies

```bash
cd ~/app
composer install --no-dev --optimize-autoloader
```

If `composer` is missing: `curl -sS https://getcomposer.org/installer | php` then use `php composer.phar`.
If PHP dependencies cannot be installed on the host at all, run `composer install --no-dev` locally and
upload `vendor/` alongside the code.

## 5. Configure the environment

```bash
cd ~/app
cp .env.production.example .env
php artisan key:generate
nano .env
```

Set at least:

```ini
APP_NAME=Piotrack
APP_ENV=production
APP_DEBUG=false
APP_URL=https://piotrack.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<from step 1>
DB_USERNAME=<from step 1>
DB_PASSWORD=<from step 1>

# No Redis on shared hosting.
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# Fixture drivers: no third-party credentials needed to click through the product.
AI_DRIVER=fixture
ANALYTICS_CALLS_DRIVER=fixture

# Allows the demo dataset to seed despite APP_ENV=production (step 7).
DEMO_SEED_ALLOWED=true
```

`APP_DEBUG=false` matters: with it on, a stack trace would expose your database credentials to anyone
who triggers an error.

## 6. Migrate

```bash
php artisan migrate --force
```

## 7. Seed the demo dataset

```bash
php artisan db:seed --class=DemoSeeder --force
```

Creates **Northwind IT Services** on the Professional plan with contacts, companies, a deal pipeline,
keywords with rankings, 30 days of ad metrics, content, reviews, bookings, a project with sprint and
tasks, three published website pages, KPI targets and a performance agreement — so every dashboard
shows real numbers.

Sign in with `demo@piotrack.test` / `piotrack-demo-2026`.

**Change that password immediately after your first login,** or delete the demo user once you have
made your own account. It is a known credential published in this repository.

## 8. Point the web root at `public/`

Preferred — hPanel → **Websites → piotrack.com → Advanced → Change website root** → set to
`/home/u120417915/app/public`.

If the root cannot be changed, put this in `~/public_html/.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTP_HOST} ^(www\.)?piotrack\.com$ [NC]
RewriteRule ^(.*)$ /app/public/$1 [L]
```

Then set permissions:

```bash
cd ~/app
chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

## 9. Cache for production

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Re-run these three after **every** deploy or `.env` change — a cached config ignores later edits.

## 10. Cron: scheduler and queue

hPanel → **Advanced → Cron Jobs**.

Scheduler, every minute:

```bash
cd /home/u120417915/app && php artisan schedule:run >> /dev/null 2>&1
```

Queue drain, every 5 minutes (shared hosting kills long-running workers, so this exits when the queue
empties instead of running forever):

```bash
cd /home/u120417915/app && php artisan queue:work --stop-when-empty --max-time=280 >> /dev/null 2>&1
```

Queued email and AI-visibility jobs will not run without this.

## 11. Verify

```bash
curl -sI https://piotrack.com | head -1          # expect HTTP/2 200
curl -s  https://piotrack.com/up                 # health check
```

Then in a browser:

1. `https://piotrack.com/login` → sign in as the demo user.
2. **Analytics** → funnel, ad KPIs and revenue should show real figures, not zeros.
3. **Website → Pages** → three published pages; open one's public URL at `/s/{slug}`.
4. **Strategy** → the five-P methodology should score around 85.
5. Public booking page at `/b/demo-assessment`.

## Deploying an update later

```bash
cd ~/app
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Front-end changes additionally need step 2 re-run locally and the branch re-pushed.

## If something breaks

| Symptom                          | Cause                                                                                           |
| -------------------------------- | ----------------------------------------------------------------------------------------------- |
| 500 with a blank page            | `storage/logs/laravel.log`; usually permissions — re-run `chmod -R 775 storage bootstrap/cache` |
| "No application encryption key"  | `php artisan key:generate` was skipped                                                          |
| Styles missing / white page      | `public/build` absent — step 2                                                                  |
| Changes to `.env` do nothing     | Cached config — re-run step 9                                                                   |
| 404 on every route but `/`       | Web root is not `public/` — step 8                                                              |
| Migration fails on an index name | Should not happen; CI's `tests-mysql` job validates MySQL migrations on every push              |

## Known limitations of this deploy

- **Email does not send.** No SMTP configured, so verification and campaign mail is logged, not
  delivered. Set `MAIL_*` in `.env` to change that.
- **Stripe is unverified.** Billing runs on the manual provider; no live payment has ever been tested.
- **AI output is the fixture driver**, clearly labelled in the UI — not a real language model.
- **No backups.** Nothing here is backed up; treat the data as disposable.
