# Publishing Piotrack to the internet

How to take the internal deployment from LAN-only to publicly reachable.

|                 | Now                         | Target                         |
| --------------- | --------------------------- | ------------------------------ |
| URL             | `http://192.168.1.230:8080` | `https://piotrack.com`         |
| Reachable from  | the office LAN              | anywhere                       |
| TLS             | none                        | Let's Encrypt, auto-renewing   |
| Exposed surface | everything                  | everything (admin UI included) |

The apex serves everything from one host: the marketing landing page at `/`, the
application behind `/login`, and the chat widget script at `/widget/`.

What is on `piotrack.com` today is a GoDaddy "coming soon" placeholder, so pointing
the apex at this server replaces a placeholder rather than a live site.

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
curl -sD - -o /dev/null http://192.168.1.230:8080/login | grep -i content-security
```

This section is done before the domain exists, so it still checks the LAN address.

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

| Control                                           | Where                                                          |
| ------------------------------------------------- | -------------------------------------------------------------- |
| HSTS, one year, includeSubDomains                 | `SecurityHeaders.php` — self-enables once `$request->secure()` |
| `X-Frame-Options: DENY`, `frame-ancestors 'none'` | `SecurityHeaders.php`                                          |
| `X-Content-Type-Options: nosniff`                 | `SecurityHeaders.php`                                          |
| Login brute-force throttling                      | `LoginRequest::ensureIsNotRateLimited()`                       |
| Session cookie `httponly`, `samesite=lax`         | `config/session.php`                                           |
| Forwarded headers ignored unless opted in         | `config/security.php` — `TRUSTED_PROXIES`, empty by default    |
| Widget API CORS scoped to `wc/*`                  | `config/cors.php`                                              |
| Per-tenant row isolation                          | `BelongsToTenant` global scope                                 |
| Public capture endpoints throttled                | `routes/chat.php` — 20–240/min per route                       |

## Steps

### 1. Find the public IP, and confirm it is static

Run on the server:

```bash
curl -s https://api.ipify.org; echo
```

Ask the ISP whether that address is static. If it is dynamic the site breaks silently
whenever it changes. GoDaddy has a domain API you can drive from cron to update the
record, but a static IP is worth the line item.

### 2. DNS — repoint the apex at GoDaddy

Edit the existing apex `A` record. It currently reads `WebsiteBuilder Site`, a
GoDaddy-managed pseudo-record pointing at their hosting; replace it with the public
IP from step 1.

| Type | Name | From                  | To                        | TTL    |
| ---- | ---- | --------------------- | ------------------------- | ------ |
| A    | `@`  | `WebsiteBuilder Site` | the public IP from step 1 | 1 Hour |

> **It must be the public IP, not `192.168.1.230`.** A `192.168.x.x` address is
> private (RFC 1918) and is not routable on the internet: a visitor's browser would
> try to reach that address on _their own_ network and find nothing. It fails in a
> way that is easy to miss, because from inside this office the address does resolve
> to the server, so the site looks fine while being dead to everyone else. The
> private address belongs in the router's port-forward rule, not in public DNS. The
> chain is `piotrack.com` → public IP → router forwards 443 → `192.168.1.230`.

`www` needs no change — the existing `CNAME www → piotrack.com` follows the apex
automatically. Leave the `NS`, `SOA`, `_domainconnect` and `_dmarc` records alone.

Two things to expect:

- GoDaddy may refuse to edit the record while a Website Builder site is attached to
  the domain. Disconnect the site in the Website Builder dashboard first; the
  placeholder is what currently answers on `piotrack.com`.
- The TTL is one hour, so allow up to that for the change to be visible everywhere.
  Certbot will fail until it resolves, which is harmless — rerun it.

Do **not** move the nameservers to Cloudflare for a tunnel. Email authentication
(`_dmarc`) and domain connect records live in this zone, and recreating them by hand
risks breaking mail deliverability to solve a hosting problem.

### 3. Firewall — inbound NAT on the SonicWall

The gateway at `192.168.1.1` is a SonicWall running SonicOS 7, not a consumer router.
There is no single "port forwarding" setting: inbound publishing needs **both** a NAT
policy and a WAN→LAN access rule. With only one of the two, traffic is silently
dropped and the symptom is indistinguishable from an ISP block.

| External | Internal            | Why                               |
| -------- | ------------------- | --------------------------------- |
| TCP 443  | `192.168.1.230:443` | the site itself                   |
| TCP 80   | `192.168.1.230:80`  | serves only the redirect to HTTPS |

Both are needed: the `:80` vhost redirects to HTTPS, so without it anyone typing the
bare hostname gets nothing.

**The straightforward route** is the Public Server Wizard (Quick Configuration →
Public Server Wizard). Choose a Web Server, service HTTP + HTTPS, private address
`192.168.1.230`. It creates the address object, the inbound NAT policy, the access
rule, and — importantly — the loopback NAT policy in one pass.

**Doing it by hand** means three pieces:

1. **Address object** — Object → Match Objects → Addresses. Host, `192.168.1.230`,
   zone LAN.
2. **NAT policy** — Policy → Rules and Policies → NAT Policies. Original destination
   is the WAN interface IP, translated destination is that host object, original
   service HTTP (then a second policy for HTTPS), inbound interface WAN.
3. **Access rule** — Policy → Rules and Policies → Access Rules, WAN → LAN, allowing
   HTTP/HTTPS to that host.

Two things that catch people out here:

- **A management-port collision.** If HTTPS Management or SSL-VPN is bound to port
  443 on the WAN interface, the NAT policy for 443 will not take effect — the
  appliance answers first. Check that before assuming the rule is wrong, and move
  management to another port if it clashes.
- **Testing from inside the LAN.** Without the loopback NAT policy, a machine on
  `192.168.1.x` cannot reach the site via the public IP, and the failure looks
  exactly like a broken rule. Test from a phone on mobile data instead. The wizard
  creates that policy; a hand-built config often omits it.

Also confirm with the ISP that inbound 80/443 are permitted on this connection and
that the public address is static. If inbound is blocked, none of the above is
reachable regardless of how the firewall is configured.

While in this appliance: the same firewall is what blocks the server's **outbound**
access, which is why the certificate has to be issued off-box (see
[certificate-renewal.md](certificate-renewal.md)). Allowing outbound 443 from
`192.168.1.230` would let certbot renew automatically and retire that manual
procedure entirely.

### 4. Apache vhost

> **Port 80 on this server is not free.** It serves a live IT Support Portal
> (osTicket) from a single catch-all vhost that answers every `Host` header. Piotrack
> is on `:8080`. Forwarding the router's port 80 straight through would publish the
> helpdesk on `piotrack.com`, not Piotrack — and the helpdesk is production.

The fix is a name-based vhost, which is additive: Apache matches `ServerName` first
and falls back to the default vhost for every other hostname, so the helpdesk keeps
answering exactly as it does now.

Check which vhost is currently the default before adding anything:

```bash
sudo apache2ctl -S
```

Apache treats the **first** `*:80` vhost it loads as the default. If the helpdesk's
config sorts after the new file alphabetically, adding Piotrack would silently make
_it_ the catch-all and take the helpdesk offline. Name the new file so it sorts after
the existing one, and confirm against that output.

`/etc/apache2/sites-available/piotrack.conf`:

```apache
<VirtualHost *:80>
    ServerName piotrack.com
    ServerAlias www.piotrack.com
    DocumentRoot /var/www/piotrack/public

    <Directory /var/www/piotrack/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/piotrack-error.log
    CustomLog ${APACHE_LOG_DIR}/piotrack-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite piotrack && sudo apache2ctl configtest && sudo systemctl reload apache2
```

`configtest` before `reload` is not optional here — a syntax error on a reload takes
the helpdesk down with it. Back up `/etc/apache2` first.

### 5. Certificate — blocked until the server has outbound access

> **This server cannot reach the internet.** `https://acme-v02.api.letsencrypt.org`
> times out from it (`curl` exit 28, status `000`) while another machine on the same
> LAN reaches it fine, so outbound is firewalled for this host specifically.

Certbot needs outbound HTTPS to the ACME API to request, validate and renew — that is
true of HTTP-01 and DNS-01 alike. `apt install certbot` needs outbound too. So there
are two ways forward:

- **Allow outbound 443 from this host** to `acme-v02.api.letsencrypt.org`. One
  firewall rule, and renewal then runs unattended every 60 days. This is the option
  worth taking.
- **Issue the certificate on a machine that has internet**, using a DNS-01 challenge
  against the GoDaddy API, then copy `fullchain.pem` and `privkey.pem` across. Works,
  but every renewal is manual and a missed one takes the site down.

Once a certificate exists, add the `*:443` vhost with the same `ServerName` and
`DocumentRoot`, plus `SSLCertificateFile` / `SSLCertificateKeyFile`.

### 6. Application configuration

In `.env`, alongside the `APP_ENV` and `APP_DEBUG` changes from above:

```
APP_URL=https://piotrack.com
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

### 7. Leave the trusted proxies empty

Nothing to do for a direct port-forward — this is the default — but it is worth
knowing why. `X-Forwarded-For` and `X-Forwarded-Proto` are client input until a proxy
is explicitly trusted; believing them unconditionally lets any caller choose the IP
the login throttle counts against, and claim a plaintext request arrived over TLS.
So `TRUSTED_PROXIES` stays empty here.

If you later put the app behind Cloudflare or a load balancer, opt in with the proxy's
addresses — not `*`, unless that proxy is the only way in:

```
TRUSTED_PROXIES=10.0.0.0/8,192.168.0.0/16
```

See `config/security.php`.

### 8. Firewall and intrusion blocking

```bash
sudo ufw allow 443/tcp && sudo ufw allow 80/tcp && sudo ufw enable
sudo apt install fail2ban
```

Keep 8080 off the public interface; it should remain LAN-only.

## Verify

```bash
curl -sD - -o /dev/null https://piotrack.com/login | grep -iE "strict-transport|content-security|set-cookie"
```

Expect all of:

- `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- a `Content-Security-Policy` with **no** `unsafe-eval`
- `piotrack_session=...; secure; httponly; samesite=lax`

Then re-copy the install snippet from **Website Chat → Widgets → Settings**. It should
now read:

```html
<script src="https://piotrack.com/widget/piotrack-chat.js" data-widget="wc_..." async></script>
```

HTTPS also restores `navigator.clipboard`, which browsers only expose in a secure
context — the `execCommand` fallback in `resources/js/lib/clipboard.ts` becomes a
safety net rather than the primary path.

## Appendix: widget-only exposure

If the goal is only for the chat widget to work on customer websites, the admin UI
does not need to be public. The widget resolves its API base from its own script tag's
origin (`resources/js/widget/embed.ts`), so a single public hostname serving two route
groups is sufficient:

| Route                          | Purpose                              |
| ------------------------------ | ------------------------------------ |
| `GET /widget/piotrack-chat.js` | the widget script, ~17 KB static     |
| `/wc/{publicKey}/*`            | config, events, start, message, poll |

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
