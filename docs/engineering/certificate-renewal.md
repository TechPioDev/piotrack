# Renewing the piotrack.com certificate

**This certificate does not renew itself.** It expires every 90 days and the site
goes down with a browser security warning when it does — no grace period, no
degraded mode.

|                 |                                                                  |
| --------------- | ---------------------------------------------------------------- |
| Names           | `piotrack.com`, `www.piotrack.com`                               |
| Issued          | 22 August 2026                                                   |
| **Expires**     | **20 November 2026**                                             |
| Renew by        | early November 2026                                              |
| Expiry warnings | Let's Encrypt emails `proapps@techpio.com` at 20 days and 7 days |

Put a calendar reminder at **1 November 2026**. The warning emails are a backstop,
not a plan — they land in one inbox and are easy to miss.

## Why it is manual

The server has no outbound internet: `acme-v02.api.letsencrypt.org` times out from
it, while other hosts on the same LAN reach it fine. Certbot needs outbound HTTPS
to request _and_ renew, so it cannot run there at all. The certificate is therefore
issued on a machine that does have internet, using a DNS-01 challenge, and copied
across.

If that server is ever allowed outbound 443, switch to `certbot --apache` on the
box itself and this whole document becomes obsolete — renewal then happens
unattended every 60 days. That is worth doing.

## Renewing

Everything lives in `C:\Users\DalbeirSingh\AppData\Local\Temp\acme` on the Windows
workstation. **Note it is under `Temp`** — if that has been cleared, redo the setup
in the first section of [public-deployment.md](public-deployment.md) before starting.

### 1. Run certbot

Right-click `get-certificate.bat` → **Run as administrator**. Certbot on Windows
refuses to run unelevated, and the script stops with a message rather than failing
halfway.

### 2. Add the TXT records at GoDaddy

The script prints one record per name and then waits, polling
`ns19.domaincontrol.com` directly until each appears — so there is no guessing about
propagation, which is the usual way a manual DNS-01 fails.

| Type | Name                  | Value                             | TTL                |
| ---- | --------------------- | --------------------------------- | ------------------ |
| TXT  | `_acme-challenge`     | printed by the script             | shortest available |
| TXT  | `_acme-challenge.www` | printed by the script (different) | shortest available |

Both must exist at the same time — Let's Encrypt validates them together after the
second one is in place. The values are new every run; a previous run's value is
never accepted.

### 3. Copy the certificate to the server

From `config\live\piotrack.com\`, `fullchain.pem` and `privkey.pem` go to
`/etc/ssl/piotrack/` on the server:

```bash
sudo install -o root -g root -m 644 fullchain.pem /etc/ssl/piotrack/fullchain.pem
sudo install -o root -g root -m 600 privkey.pem  /etc/ssl/piotrack/privkey.pem
sudo systemctl reload apache2
```

The private key must be `600` and root-owned. Apache reads it as root before
dropping privileges, so `www-data` never needs access — if the key is readable by
`www-data`, any code execution flaw in either app on that box leaks it.

Transfer the key over something encrypted (`scp`), not the plain-HTTP file server
used for code deploys. It is a private key on a shared LAN.

### 4. Delete the old TXT records

They serve no purpose once the certificate is issued, and leaving them accumulating
in the zone makes it harder to tell which run each belongs to.

### 5. Verify

```bash
echo | openssl s_client -connect piotrack.com:443 -servername piotrack.com 2>/dev/null | openssl x509 -noout -dates -subject -ext subjectAltName
```

Check `notAfter` is about 90 days out and both names are listed. Then load
`https://piotrack.com` in a browser and confirm there is no warning.
