# Publishing Piotrack to the internet

How to take the internal deployment from LAN-only to publicly reachable.

| | Now | Target |
| --- | --- | --- |
| URL | `http://192.168.1.230:8080` | `https://app.piotrack.com` |
| Reachable from | the office LAN | anywhere |
| TLS | none | Let's Encrypt, auto-renewing |
| Exposed surface | everything | everything (admin UI included) |

Substitute your own subdomain label for `app` throughout; any label works, and the
same host serves both the admin UI and the chat widget script.

> **Scope note.** This publishes the entire application, including `/login`,
> `/dashboard` and every tenant's CRM data. A narrower option exists — publish only
> `/widget/*` and `/wc/*`, which is all the embeddable chat needs, and keep the
> admin UI on the LAN. See [Appendix: widget-only exposure](#appendix-widget-only-exposure).

## Before you open the port

These are not optional polish. Each one is a real hole if skipped.

### 1. Switch the app to production mode

The live server currently reports a development Content-Security-Policy
(`script-src` includes `'unsafe-inline'` and `'unsafe-eval'`), which means
`APP_ENV` is not `production`. Two consequences:

- The CSP does not tighten. `contentSecurityPolicy()` in
  `app/Http/Middleware/SecurityHeaders.php` only restricts `script-src` to `'self'`
  when `app()->isProduction()` is true, so XSS protection is materially weaker.
- `APP_DEBUG` is probably `true`. Laravel's debug error page renders environment
  variables — database password, `APP_KEY`, mail credentials — to anyone who can
  trigger a 500.

In `.env`:

```
APP_ENV=production
APP_DEBUG=false
```

Verify afterwards; the CSP must come back without `unsafe-eval`:

```bash
curl -sD - -o /dev/null https://app.piotrack.com/login | grep -i content-security
```

### 2. Rotate anything that leaked while debug was on

If the server has ever returned a debug stack trace to a browser, treat the
credentials in it as disclosed. At minimum rotate the database password. `APP_KEY`
rotation invalidates existing sessions and any encrypted column, so plan that one.

### 3. Set each widget's allowed domains

New widgets are created with `'allowed_domains' => []`. Empty means the origin check
in `PublicChatController::resolveWidget()` is skipped entirely, and the widget key is
visible in the page source of every site it is installed on. Set the customer's
domain in **Website Chat → Widgets → Settings** before the endpoint is public.

### 4. Require two-factor for anyone with tenant data access

`TwoFactorChallengeController` already exists. Once the login page is internet-facing,
password-only access to a multi-tenant CRM is the weakest link. Enrol every admin.

### 5. Take a restorable backup

Not a snapshot you have never restored — an actual verified restore. Do this before
the port opens, not after.

## What is already handled

Verified in the codebase; no action needed.

| Control | Where |
| --- | --- |
| HSTS, one year, includeSubDomains | `SecurityHeaders.php` — self-enables once `$request->secure()` |
| `X-Frame-Options: DENY`, `frame-ancestors 'none'` | `SecurityHeaders.php` |
| `X-Content-Type-Options: nosniff` | `SecurityHeaders.php` |
| Login brute-force throttling | `LoginRequest::ensureIsNotRateLimited()` |
| Session cookie `httponly`, `samesite=lax` | `config/session.php` |
| Forwarded-proto handling behind a proxy | `bootstrap/app.php` — `trustProxies` |
| Widget API CORS scoped to `wc/*` | `config/cors.php` |
| Per-tenant row isolation | `BelongsToTenant` global scope |
| Public capture endpoints throttled | `routes/chat.php` — 20–240/min per route |

## Steps

### 1. Find the public IP, and confirm it is static

Run on the server:

```bash
curl -s https://api.ipify.org; echo
```

Ask the ISP whether that address is static. If it is dynamic the site breaks silently
whenever it changes. GoDaddy has a domain API you can drive from cron to update the
record, but a static IP is worth the line item.

### 2. DNS — one new record at GoDaddy

Everything already in the zone stays untouched. The apex `A @ → WebsiteBuilder Site`
and `CNAME www → piotrack.com` keep serving the existing website.

| Type | Name | Data | TTL |
| --- | --- | --- | --- |
| A | `app` | the public IP from step 1 | 1 Hour |

Do **not** move the nameservers to Cloudflare for a tunnel. The apex A record is a
GoDaddy-managed pseudo-record whose addresses can change without notice; recreating
it by hand elsewhere puts the live website at risk to solve a chat problem.

### 3. Router — forward inbound ports

| External | Internal |
| --- | --- |
| TCP 443 | `192.168.1.230:443` |
| TCP 80 | `192.168.1.230:80` |

Port 80 is needed for certbot's HTTP-01 challenge and every renewal. These are 443/80,
not 8080 — the existing `:8080` vhost keeps working on the LAN unchanged.

### 4. Apache vhost and certificate

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d app.piotrack.com
```

Point the new vhost's `DocumentRoot` at `/var/www/piotrack/public`, matching the 8080
vhost. Certbot installs a renewal timer; confirm it with `sudo certbot renew --dry-run`.

### 5. Application configuration

In `.env`, alongside the `APP_ENV` and `APP_DEBUG` changes from above:

```
APP_URL=https://app.piotrack.com
SESSION_SECURE_COOKIE=true
```

`APP_URL` is what `url()` uses to build the widget install snippet, so this is what
turns the copied `<script src="...">` into a public HTTPS URL.

```bash
cd /var/www/piotrack && sudo -u www-data php artisan config:cache
```

Always run artisan as `www-data`. Running it as your login user clears the config
cache and then fails to re-read `.env`, and Laravel silently falls back to framework
defaults — including `DB_CONNECTION=sqlite`, which is why a bare `php artisan
optimize:clear` reports a missing `database.sqlite` on a Postgres install.

### 6. Narrow the trusted proxies

`bootstrap/app.php` sets `trustProxies(at: '*')`. Trusting every proxy is fine on a
LAN, but once the app is internet-facing any client can spoof `X-Forwarded-For` and
defeat the per-IP login throttle. With a direct port-forward there is no proxy in
front, so this should become an explicit list — or `null`.

### 7. Firewall and intrusion blocking

```bash
sudo ufw allow 443/tcp && sudo ufw allow 80/tcp && sudo ufw enable
sudo apt install fail2ban
```

Keep 8080 off the public interface; it should remain LAN-only.

## Verify

```bash
curl -sD - -o /dev/null https://app.piotrack.com/login | grep -iE "strict-transport|content-security|set-cookie"
```

Expect all of:

- `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- a `Content-Security-Policy` with **no** `unsafe-eval`
- `piotrack_session=...; secure; httponly; samesite=lax`

Then re-copy the install snippet from **Website Chat → Widgets → Settings**. It should
now read:

```html
<script src="https://app.piotrack.com/widget/piotrack-chat.js" data-widget="wc_..." async></script>
```

HTTPS also restores `navigator.clipboard`, which browsers only expose in a secure
context — the `execCommand` fallback in `resources/js/lib/clipboard.ts` becomes a
safety net rather than the primary path.

## Appendix: widget-only exposure

If the goal is only for the chat widget to work on customer websites, the admin UI
does not need to be public. The widget resolves its API base from its own script tag's
origin (`resources/js/widget/embed.ts`), so a single public hostname serving two route
groups is sufficient:

| Route | Purpose |
| --- | --- |
| `GET /widget/piotrack-chat.js` | the widget script, ~17 KB static |
| `/wc/{publicKey}/*` | config, events, start, message, poll |

In the 443 vhost, allow those and refuse everything else:

```apache
<Location "/">
    Require all denied
</Location>
<Location "/widget/">
    Require all granted
</Location>
<LocationMatch "^/wc/">
    Require all granted
</LocationMatch>
```

This reduces the internet-facing surface from the entire application to six routes,
none of which is authenticated or reaches tenant data beyond the widget's own
conversation rows.
