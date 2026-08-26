# Runbook — Deploying piotrack on your own Ubuntu server

For a server you have root on — your own hardware, a Hostinger/Hetzner/DigitalOcean VPS, or the
existing `ticketingserver` box. This is the **preferred** deployment: everything piotrack was designed
for works properly here, unlike shared hosting.

Written against the internal server at `192.168.1.230` (`ticketingserver`, Ubuntu, Apache 2.4.66).
Substitute your own host and paths as needed.

---

## ⚠️ Read this first: that server runs a live osTicket helpdesk

`192.168.1.230` is serving **osTicket** on port 80 (`/scp` agent panel, active `OSTSESSID` sessions).
Assume people depend on it.

**The danger is PHP.** Both applications are PHP. osTicket 1.14/1.16 does **not** run on PHP 8.2+;
piotrack **requires** 8.2+. If you upgrade the system or Apache PHP version, the helpdesk breaks.

This runbook therefore never touches osTicket's PHP. It installs PHP 8.4 **alongside**, runs piotrack
under its own PHP-FPM pool and its own Apache vhost, and leaves the existing configuration alone.

Before you start:

```bash
# What PHP is osTicket actually using? Note this — you are not changing it.
php -v
apache2ctl -M | grep -iE "php|proxy_fcgi"
ls /etc/apache2/sites-enabled/

# Back up osTicket's database and files first. Non-negotiable.
sudo mysqldump -u root -p osticket > ~/osticket-backup-$(date +%F).sql
sudo tar czf ~/osticket-files-$(date +%F).tar.gz /var/www/html
```

If `php -v` reports 8.1 or older, that confirms it: **do not** run `apt upgrade php` or change the
default `php` alternative at any point.

You also need sudo. Check with `sudo -v` — if the `ticketingserver` account has no sudo rights, get
them before continuing.

---

## 1. The stack

```bash
sudo apt update

# PHP 8.4 from the Ondrej PPA — installed alongside, not replacing, the existing PHP.
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl php8.4-redis

# PostgreSQL 16 — the dialect piotrack targets and CI validates.
sudo apt install -y postgresql postgresql-contrib

# Redis for cache, sessions and queues.
sudo apt install -y redis-server
sudo systemctl enable --now redis-server

# Composer, Node 22, git.
sudo apt install -y git unzip
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

Confirm osTicket still works before going further — open `http://192.168.1.230` in a browser. Adding
`php8.4-fpm` should not have changed the Apache handler, but verify rather than assume.

## 2. Database

```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE piotrack;
CREATE USER piotrack WITH ENCRYPTED PASSWORD 'use-a-long-random-password-here';
GRANT ALL PRIVILEGES ON DATABASE piotrack TO piotrack;
\c piotrack
GRANT ALL ON SCHEMA public TO piotrack;
\q
```

Generate the password rather than inventing one: `openssl rand -base64 24`.

## 3. Get the code

```bash
sudo mkdir -p /var/www/piotrack
sudo chown -R $USER:www-data /var/www/piotrack
git clone https://github.com/Dalbeirdev/piotrack.git /var/www/piotrack
cd /var/www/piotrack

composer install --no-dev --optimize-autoloader
npm ci && npm run build          # Node is available here — no local build needed
```

## 4. Configure

```bash
cp .env.production.example .env
php8.4 artisan key:generate
nano .env
```

```ini
APP_NAME=Piotrack
# staging, not production: the app only forces HTTPS when APP_ENV=production,
# so this keeps plain HTTP working on the LAN. Switch to production once TLS is real.
APP_ENV=staging
APP_DEBUG=false
APP_URL=http://piotrack.local

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=piotrack
DB_USERNAME=piotrack
DB_PASSWORD=<from step 2>

# Redis is available on a real server — use it.
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

# Fixture drivers: no third-party credentials needed to exercise the product.
AI_DRIVER=fixture
ANALYTICS_CALLS_DRIVER=fixture

DEMO_SEED_ALLOWED=true
```

`APP_DEBUG=false` matters — with it on, any error page prints your database credentials.

```bash
php8.4 artisan migrate --force
php8.4 artisan db:seed --class=DemoSeeder --force
php8.4 artisan storage:link

sudo chown -R www-data:www-data /var/www/piotrack/storage /var/www/piotrack/bootstrap/cache
sudo chmod -R 775 /var/www/piotrack/storage /var/www/piotrack/bootstrap/cache
```

## 5. Its own PHP-FPM pool

A separate pool keeps piotrack's PHP isolated from osTicket's.

```bash
sudo nano /etc/php/8.4/fpm/pool.d/piotrack.conf
```

```ini
[piotrack]
user = www-data
group = www-data
listen = /run/php/php8.4-fpm-piotrack.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 20M
```

```bash
sudo systemctl restart php8.4-fpm
sudo systemctl enable php8.4-fpm
```

## 6. Its own Apache vhost

osTicket keeps port 80 on the IP; piotrack answers on its own hostname.

```bash
sudo a2enmod proxy_fcgi setenvif rewrite
sudo nano /etc/apache2/sites-available/piotrack.conf
```

```apache
<VirtualHost *:80>
    ServerName piotrack.local

    DocumentRoot /var/www/piotrack/public

    <Directory /var/www/piotrack/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # Only this vhost uses PHP 8.4 — osTicket's handler is untouched.
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:/run/php/php8.4-fpm-piotrack.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog  ${APACHE_LOG_DIR}/piotrack-error.log
    CustomLog ${APACHE_LOG_DIR}/piotrack-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite piotrack
sudo apache2ctl configtest      # must say Syntax OK before you reload
sudo systemctl reload apache2
```

**Then immediately confirm osTicket still loads** at `http://192.168.1.230`. If the vhost is
name-based and osTicket's is the default, it stays first for bare-IP requests — but check, don't hope.

## 7. Queue worker as a real service

The thing shared hosting cannot do: a worker that stays alive.

```bash
sudo nano /etc/systemd/system/piotrack-worker.service
```

```ini
[Unit]
Description=piotrack queue worker
After=network.target redis-server.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php8.4 /var/www/piotrack/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now piotrack-worker
sudo systemctl status piotrack-worker
```

## 8. Scheduler

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/piotrack && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

This drives the nightly growth-score snapshots, AI-visibility checks, booking reminders, campaign sends
and retention pruning.

## 9. Name resolution

`192.168.1.230` is a private address, so pick one:

**Internal DNS** (best) — add an A record on your router / pfSense / AD DNS:
`piotrack.local → 192.168.1.230`. Works for every device on the network.

**Hosts file** (instant, per machine) — on Windows, edit
`C:\Windows\System32\drivers\etc\hosts` as Administrator:

```
192.168.1.230    piotrack.local
```

Avoid pointing a _public_ DNS record at `192.168.1.230`: it publishes your internal addressing, and
DNS-rebinding protection in browsers and routers will often refuse to resolve it anyway.

## 10. Cache for production

```bash
cd /var/www/piotrack
php8.4 artisan config:cache && php8.4 artisan route:cache && php8.4 artisan view:cache
```

Re-run after **every** deploy or `.env` edit — a cached config ignores later changes.

## 11. Verify

```bash
curl -sI -H "Host: piotrack.local" http://192.168.1.230 | head -1   # HTTP/1.1 200
curl -s  -H "Host: piotrack.local" http://192.168.1.230/up          # health check
sudo systemctl is-active piotrack-worker redis-server php8.4-fpm
curl -sI http://192.168.1.230 | head -1                             # osTicket STILL 200
```

Then in a browser at `http://piotrack.local`:

1. Sign in as `demo@piotrack.test` / `piotrack-demo-2026` — **change this password immediately.**
2. **Analytics** — funnel, ad KPIs and revenue show real figures.
3. **Website → Pages** — three published pages; open one at `/s/{slug}`.
4. **Strategy** — five-P methodology scores ~85.
5. Public booking page at `/b/demo-assessment`.

## 12. TLS, when you want it

On a LAN-only host Let's Encrypt cannot validate over HTTP. Options:

- **Stay on HTTP** with `APP_ENV=staging` — fine for internal testing on a trusted network.
- **Self-signed** — `sudo apt install ssl-cert`, add a `*:443` vhost using
  `/etc/ssl/certs/ssl-cert-snakeoil.pem`. One browser warning per machine.
- **Real cert via DNS-01** — `sudo apt install certbot`, then
  `sudo certbot certonly --manual --preferred-challenges dns -d piotrack.yourdomain.com`. Works
  without public inbound; needs a TXT record you can add.

When TLS is live, set `APP_ENV=production` and `APP_URL=https://…`, then re-run step 10.

## Deploying updates

```bash
cd /var/www/piotrack
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php8.4 artisan migrate --force
php8.4 artisan config:cache && php8.4 artisan route:cache && php8.4 artisan view:cache
sudo systemctl restart piotrack-worker      # picks up the new code
```

## Moving to the real production server later

Everything above is portable. On the new box: repeat steps 1–11, then move the data:

```bash
# On the test server
pg_dump -U piotrack piotrack > piotrack.sql
tar czf storage.tar.gz storage/app

# On the new server, after step 3
psql -U piotrack piotrack < piotrack.sql
tar xzf storage.tar.gz
```

Set `APP_ENV=production` with real TLS, swap the fixture drivers for real credentials, and configure
backups per [backup-and-disaster-recovery.md](backup-and-disaster-recovery.md).

## Troubleshooting

| Symptom                        | Cause                                                                                                                                           |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| **osTicket broke**             | Stop. `sudo a2dissite piotrack && sudo systemctl reload apache2`, then check `php -v` is unchanged and restore from the step-0 backup if needed |
| 500, blank page                | `tail -50 /var/www/piotrack/storage/logs/laravel.log`; usually storage permissions                                                              |
| 502 Bad Gateway                | FPM socket path mismatch — compare the pool `listen=` with the vhost `SetHandler`                                                               |
| Styles missing                 | `npm run build` not run, or `public/build` unreadable by www-data                                                                               |
| `.env` changes ignored         | Cached config — re-run step 10                                                                                                                  |
| Queued work never runs         | `sudo systemctl status piotrack-worker`                                                                                                         |
| `piotrack.local` won't resolve | Step 9 not done on the machine you're browsing from                                                                                             |

## What this deployment still does not do

- **No email delivery** — no SMTP configured; verification and campaign mail is logged, not sent.
- **Stripe unverified** — billing runs on the manual provider; no live payment has ever been processed.
- **AI is the fixture driver**, labelled as such in the UI — not a real language model.
- **No backups configured** — see the DR runbook before this holds anything you care about.
- **LAN-only** — not reachable off the network without a VPN, port forward or tunnel.
