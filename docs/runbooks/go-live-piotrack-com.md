# Go-Live Runbook — piotrack.com on Laravel Cloud

Target: deploy the current app (Stage 0–1: identity/auth) as a live **preview** at
`https://piotrack.com`. Host: **Laravel Cloud** (managed PostgreSQL 16 + Redis, auto-TLS).

> What is NOT yet in the product at this stage: billing, multi-tenancy, RBAC, and the product
> modules (Stages 2–14). This deployment proves the pipeline and puts sign-up/login online.

The repo is already prepared for this (trusted proxies, forced HTTPS in prod, `DB_SSLMODE`
support, `.env.production.example`). The steps below are the parts only you can do — they need
your Laravel Cloud account, GitHub authorization, and registrar access.

---

## 1. Create the Laravel Cloud project (you)

1. Sign in at **cloud.laravel.com** and create an organization/project.
2. **Connect GitHub** and select the repo **`Dalbeirdev/piotrack`**, branch **`main`**.
3. Create an environment named **`production`**.

## 2. Add managed infrastructure (Laravel Cloud dashboard)

1. **Database** → add **PostgreSQL 16**. Cloud injects `DB_*` automatically; if you use an
   external managed DB instead, set `DB_SSLMODE=require`.
2. **Cache/Queue** → add a **Redis** (KeyValue) store. Cloud injects `REDIS_*`.
3. Enable a **queue worker** (processes `redis` queue) and the **scheduler** — both needed once
   Stage 4+ background jobs land; harmless to enable now.

## 3. Environment variables

Paste the values from [`.env.production.example`](../../.env.production.example) into the
environment's variables, then:

- Generate the app key: run `php artisan key:generate --show` locally and paste into **`APP_KEY`**
  (or use Cloud's "generate" button).
- Set **`APP_URL=https://piotrack.com`** and **`SESSION_DOMAIN=.piotrack.com`**.
- Configure a real **mail provider** (SES/Postmark/Mailgun) — email verification and password
  reset send through it. Without it, users can't verify.
- Leave `DB_*` / `REDIS_*` to Cloud's injected values unless using external services.

## 4. Build & deploy commands

Set these in the environment's **Deploy settings** (also mirrored in
[`.laravel-cloud/deploy.md`](../../.laravel-cloud/deploy.md)):

**Build command**

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

**Deploy (release) command**

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

## 5. First deploy

Trigger a deploy. Watch the build log; on success the app is reachable at the Cloud-provided
`*.laravel.cloud` URL. Open **`/health`** — it must return `{"status":"ok"}` with database and
cache checks passing (this confirms Postgres + Redis wiring before the domain is attached).

## 6. Attach the domain piotrack.com (you)

1. In the environment → **Domains**, add `piotrack.com` (and `www.piotrack.com`).
2. Laravel Cloud shows the exact DNS records. Add them at your registrar:
    - Apex `piotrack.com` → the record Cloud specifies (usually a **CNAME/ALIAS** to the Cloud
      hostname, or an **A** record if it gives an IP).
    - `www` → **CNAME** to the Cloud hostname.
3. Wait for DNS propagation; Cloud provisions the TLS certificate automatically.
4. Verify **https://piotrack.com/health** returns `ok` and **https://piotrack.com/login** loads.

## 7. Post-launch smoke test

- Register a real account → confirm the verification email arrives and the link verifies.
- Log in; enable 2FA; log out and back in through the challenge.
- Confirm HTTP → HTTPS redirect and that the padlock/cert is valid for piotrack.com.
- Check the health endpoint reports the deployed `APP_VERSION`.

## 8. Ongoing deploys

Every push to `main` that passes CI can auto-deploy (enable "auto-deploy on push" in Cloud).
The CI quality gate (`.github/workflows/ci.yml`) still runs first; keep it required so a red
build never reaches production.

---

### What I (Claude) cannot do from here — needs you

- Create the Laravel Cloud account and authorize GitHub.
- Enter any billing/payment for the host.
- Add DNS records at your registrar (I can't access it).
- Paste secrets (mail credentials, app key) into the dashboard.

Everything the platform _reads from the repo_ is already in place. Ping me if a build or migration
step errors and I'll debug from the logs.
