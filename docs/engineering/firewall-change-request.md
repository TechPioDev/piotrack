# Firewall change request — publish piotrack.com

For whoever administers the SonicWall at `192.168.1.1` (SonicOS 7).

## What is being asked for

Publish an internal web server to the internet on `piotrack.com`, and stop the
appliance itself from intercepting port 443 on the WAN.

|                 |                                                 |
| --------------- | ----------------------------------------------- |
| Public hostname | `piotrack.com`, `www.piotrack.com`              |
| Public address  | `14.194.100.170` (already in DNS at GoDaddy)    |
| Internal server | `192.168.1.230`                                 |
| Ports           | TCP 443 (site), TCP 80 (redirect to HTTPS only) |

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
  `192.168.1.230`, services HTTP and HTTPS, inbound interface WAN.
- **Access rule**: WAN → LAN, allow HTTP/HTTPS to that host.
- **Loopback NAT policy**: so machines on the LAN can reach the site by its public
  name. Without it, internal testing fails in a way that looks identical to a broken
  rule.

Quick Configuration → **Public Server Wizard** (Web Server, HTTP + HTTPS,
`192.168.1.230`) creates all three together.

## Change 3 — optional, but retires a recurring manual task

Allow **outbound** TCP 443 from `192.168.1.230` to `acme-v02.api.letsencrypt.org`.

That host currently has no outbound internet access — ICMP and HTTP both fail from it
while other hosts on the same subnet succeed. Because of that, its TLS certificate
has to be issued on a different machine and copied across by hand every 90 days, and
the site goes down with a browser security warning if anyone forgets. One outbound
rule makes renewal automatic and unattended.

If the isolation is deliberate, this can be declined — the manual procedure is
documented in [certificate-renewal.md](certificate-renewal.md). It is a standing
operational risk rather than a blocker.

## How to verify

From outside the network — mobile data, not office wifi:

```bash
curl -sD - -o /dev/null https://piotrack.com/ | head -1
echo | openssl s_client -connect piotrack.com:443 -servername piotrack.com 2>/dev/null | openssl x509 -noout -issuer
```

Expect `HTTP/1.1 200` and an issuer of `Let's Encrypt`. Anything mentioning SonicWALL
means change 1 has not taken effect.

## What this exposes, so the risk is understood

`192.168.1.230` will be reachable from the internet on 80 and 443. That host also
runs a production IT Support Portal (osTicket) on port 80, served by a catch-all
virtual host — it answers any hostname that is not `piotrack.com`. **Publishing port
80 therefore also publishes the helpdesk** to anyone who reaches that address by IP.

If that is not intended, restrict the change to port 443 only. The site will still
work; visitors typing the bare hostname without `https://` will get nothing rather
than a redirect.
