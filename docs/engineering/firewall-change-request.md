# Firewall change request — publish piotrack.com

For whoever administers the SonicWall at `192.168.1.1` (SonicOS 7).

## What is being asked for

Publish an internal web server to the internet on `piotrack.com` over HTTPS only,
and stop the appliance itself from intercepting port 443 on the WAN.

|                 |                                              |
| --------------- | -------------------------------------------- |
| Public hostname | `piotrack.com`, `www.piotrack.com`           |
| Public address  | `14.194.100.170` (already in DNS at GoDaddy) |
| Internal server | `192.168.1.230`                              |
| Ports           | **TCP 443 only** — deliberately not port 80  |

The server, its TLS certificate and its Apache configuration are all done and
verified. The firewall is the only remaining piece.

## Change 1 — stop the appliance answering on WAN 443

**This is the blocking problem.** A browser reaching `https://piotrack.com` from the
internet currently gets a certificate error, `NET::ERR_CERT_AUTHORITY_INVALID`.

The certificate being returned is not the site's. The site has a valid Let's Encrypt
certificate:

```
subject = CN=piotrack.com
issuer  = C=US, O=Let's Encrypt, CN=YE1
```

The appliance's own management certificate is self-signed and would produce exactly
that browser error:

```
subject = CN=192.168.168.168, O=HTTPS Management Certificate for SonicWALL (self-signed)
```

So something on the appliance is answering port 443 on the WAN before the NAT policy
can forward it. Two candidates:

1. **Network → Interfaces → WAN (X1) → Edit → Management** — if **HTTPS** is ticked,
   untick it.
2. **SSL VPN → Server Settings** — if it is bound to the WAN on port 443, move it to
   another port (8443 is conventional).

Unticking HTTPS management on the WAN is worth doing on its own merits: firewall
administration reachable from the internet is a standing risk, and SonicWall admin
interfaces are actively scanned for. LAN management at `192.168.1.1` is unaffected.

## Change 2 — confirm the inbound NAT and access rule exist

Evidence suggests these may already be in place — the real server has answered at
least once from outside — but please confirm both, since one without the other fails
silently.

- **NAT policy**: original destination = WAN interface IP, translated destination =
  `192.168.1.230`, service **HTTPS only**, inbound interface WAN.
- **Access rule**: WAN → LAN, allow **HTTPS only** to that host.
- **Loopback NAT policy**: so machines on the LAN can reach the site by its public
  name. Without it, internal testing fails in a way that looks identical to a broken
  rule.

Quick Configuration → **Public Server Wizard** (Web Server, `192.168.1.230`) creates
all three together — but **deselect HTTP and leave only HTTPS**. The wizard offers
both by default, and HTTP is specifically not wanted here (see the last section).

If a NAT policy or access rule for port 80 already exists from an earlier attempt,
please remove it.

## Change 3 — outbound 443 to two named services

Allow **outbound** TCP 443 from `192.168.1.230` to these hosts, and nothing wider:

| Destination                    | Why                                    |
| ------------------------------ | -------------------------------------- |
| `acme-v02.api.letsencrypt.org` | Automatic TLS certificate renewal      |
| `api.anthropic.com`            | The application's AI answering service |

The host currently has no outbound internet access — ICMP and HTTP both fail from it
while other hosts on the same subnet succeed. Two consequences:

- The TLS certificate has to be issued on a different machine and copied across by
  hand every 90 days, and the site goes down with a browser security warning if
  anyone forgets. The Let's Encrypt rule makes renewal automatic and unattended.
- The application's chat assistant answers visitor questions through an AI provider's
  API. Without the outbound rule it runs on a built-in placeholder, so this rule is
  what makes that feature real. `api.anthropic.com` is the currently chosen provider;
  if the operator later switches provider in the application, the destination becomes
  `api.openai.com` or `generativelanguage.googleapis.com` instead — same rule shape,
  different host.

This stays scoped to named destinations on purpose: it is not a request for general
internet access, and everything else about the host's isolation stands. If the
isolation policy cannot admit even these, the certificate fallback is the manual
procedure in [certificate-renewal.md](certificate-renewal.md), and the AI feature
stays on its placeholder — operational costs rather than blockers, but recurring ones.

To verify from the server itself once applied:

```bash
curl -sS -m 15 -o /dev/null -w "letsencrypt: %{http_code}\n" https://acme-v02.api.letsencrypt.org/directory
curl -sS -m 15 -o /dev/null -w "anthropic:   %{http_code}\n"  https://api.anthropic.com/v1/messages
```

Any HTTP status at all (200, 401, 405 — anything but a timeout) means the path is
open; the application supplies its own credentials.

## How to verify

From outside the network — mobile data, not office wifi:

```bash
curl -sD - -o /dev/null https://piotrack.com/ | head -1
echo | openssl s_client -connect piotrack.com:443 -servername piotrack.com 2>/dev/null | openssl x509 -noout -issuer
```

Expect `HTTP/1.1 200` and an issuer of `Let's Encrypt`. Anything mentioning SonicWALL
means change 1 has not taken effect.

## Why port 80 is deliberately excluded

`192.168.1.230` also runs a production IT Support Portal (osTicket) on port 80, served
by a catch-all virtual host that answers **any** hostname. Publishing port 80 would
therefore publish the helpdesk to anyone who reached that address by IP, whether or
not they knew the hostname. That is not wanted, so the request is HTTPS only.

The trade-off is small. Apache does have a `:80` vhost that redirects `piotrack.com`
to HTTPS, and it stays in place for use on the LAN — it simply will not be reachable
from outside. What that costs externally:

- Current browsers try HTTPS first for a hostname typed into the address bar, so
  `piotrack.com` reaches the site normally.
- The site sends HSTS with a one-year lifetime, so after a visitor's first successful
  visit their browser upgrades `http://` to `https://` by itself, without asking the
  network.
- Only an explicit, hand-written `http://piotrack.com` link, from a browser that has
  never visited before, fails to connect.

Keeping the helpdesk off the internet is worth that.

## What remains exposed

`192.168.1.230:443` becomes reachable from the internet. That is the whole
application, not only the public marketing pages — the login page, and behind it
multi-tenant CRM data. Before this goes live, two-factor authentication should be
enrolled for every account with data access. Login is rate-limited per IP already.
