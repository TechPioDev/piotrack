# Operator Checklist — the last 32 register rows

**As of 2026-09-09 · Register: 1,158/1,190 buildable Tested (97.3%) · 65/75 modules complete.**

Everything buildable in code is built and tested. Each remaining row needs an input only the
operator can provide — a server change, a vendor account, a designer, or authored content.
Every item below says exactly what to do, what register rows it unlocks, and where Claude
finishes the code half afterwards. Work the tracks in any order; items are ordered by
value-for-effort within each track.

Production reality check (overrides older notes): the live server is **ticketingserver**
(Ubuntu, Apache, **MySQL** — not the Postgres/Laravel Cloud wording in some Stage-era notes),
app at `/var/www/piotrack`, health at `/health`.

---

## Track A — Production server (needs sudo on ticketingserver)

### A1. Automated database + file backups — unlocks BCK-001, BCK-002 · ~1 hour
1. Nightly dump, compressed, 14-day retention:
   ```bash
   sudo tee /etc/cron.d/piotrack-backup >/dev/null <<'CRON'
   30 2 * * * root mysqldump --single-transaction piotrack | gzip > /var/backups/piotrack-db-$(date +\%F).sql.gz && find /var/backups -name 'piotrack-db-*.gz' -mtime +14 -delete
   45 2 * * 0 root tar -czf /var/backups/piotrack-files-$(date +\%F).tar.gz -C /var/www/piotrack storage/app && find /var/backups -name 'piotrack-files-*.gz' -mtime +60 -delete
   CRON
   ```
2. Copy off the machine (the part that makes it a real backup): `rclone`/`scp` the
   `/var/backups` files to a second box, NAS, or object storage on the same cron cadence.
3. For point-in-time recovery, enable MySQL binary logs (`log_bin` in mysqld.cnf).

### A2. One real restore drill — unlocks BCK-003 · ~1 afternoon
Follow `docs/runbooks/backup-and-disaster-recovery.md` exactly once:
1. Create a scratch database, restore last night's dump into it.
2. Point a throwaway `.env` at it and run **`php artisan backup:verify`** — the shipped
   command that proves the restore is usable (tables, migrations, data).
3. Record the time it took (your real RTO) and the dump age (your real RPO) in the runbook.
That single exercised drill flips BCK-003 to Tested honestly.

### A3. Redis + Horizon — unlocks JOBS-004 (module → 100%) · ~1 hour + Claude
1. `sudo apt install redis-server` and set `QUEUE_CONNECTION=redis` in the app `.env`.
2. Tell Claude — the code half (composer require laravel/horizon, config, dashboard behind
   `admin.platform`, systemd worker unit) is a prepared phase.

### A4. Metrics backend — unlocks OBS-002 (module → 100%) · ~1 hour
Easiest honest path: install **Netdata** (one command, instant latency/error/DB dashboards)
or Prometheus+Grafana if you prefer. Independently — takes 5 minutes and needs no install —
point a free uptime monitor (UptimeRobot-class) at `https://piotrack.com:5050/health`; the
endpoint already returns 503 on degradation and the new in-app admin alerting (P55) mails
platform admins on failures.

### A5. Least-privilege database/service accounts — unlocks SEC-007 (module → 100%) · ~1 hour
Create three MySQL users and record them in the DR runbook: `piotrack_app` (DML only, no
DDL), `piotrack_migrate` (DDL, used only by release.sh migrations), `piotrack_backup`
(SELECT/LOCK TABLES only, used by the A1 cron). Swap the app `.env` to the app user.

### A6. Staging environment — unlocks DEVX-003, then DEVX-004 · ½ day + Claude
Any box or VM (a second vhost + database on ticketingserver is the budget option; a separate
VM is the honest one). Give Claude the host and the CI staging-deploy + smoke-test gate
(DEVX-004) is a prepared phase.

---

## Track B — Vendor accounts & API keys (browser + billing card)

### B1. Google Cloud OAuth app — unlocks INTG-004 · ~45 min
console.cloud.google.com → new project → OAuth consent screen → OAuth client (web).
Enable the APIs you want: Search Console, Business Profile, Analytics, Google Ads.
Put client id/secret in `.env` (`services.connectors.google` — the tested generic OAuth
flow does the rest; no code). This is the highest-leverage signup: it also feeds the live
GSC driver (Search Health page) and the GBP push seam (branch profiles).

### B2. Meta developer app — unlocks half of INTG-006 · ~45 min
developers.facebook.com → app with Marketing API. The same account issues an **Ad Library
API token**, which flips the competitor-ads panel from fixture to live.

### B3. LinkedIn developer app — with B2 completes INTG-006 · ~30 min
developer.linkedin.com → app with Marketing API access.

### B4. YouTube Data API — completes INTG-006, unlocks POD-008 (module → 100%) · ~20 min + Claude
In the B1 Google project, enable **YouTube Data API v3** and add the scope to the OAuth
client. Tell Claude — the upload connector (the one thing POD-008 ever needed) becomes a
prepared phase.

### B5. Microsoft/Azure app registration — unlocks INTG-005 · ~45 min
portal.azure.com → App registrations → new app (Graph scopes for 365/Outlook/Teams;
Microsoft Ads separately if wanted). Credentials into `services.connectors.microsoft`.

### B6. CRM connectors — unlocks INTG-007 · ~15 min
HubSpot: create a private-app token — the api_key connector is **connectable today**.
Salesforce: a connected app on the generic OAuth flow when you need it.

### B7. Comms connectors — unlocks INTG-008 · ~30 min each
Twilio (flips live SMS through the tested seam), SendGrid or Mailgun (live email sending
through the tested MailProvider), Slack app, Zoom. Twilio/SendGrid/Mailgun are api_key
connectors — connectable the day the account exists.

### B8. Ops connectors — unlocks INTG-009 · varies
Stripe **live keys** (the billing module's live half — everything already rides the tested
PaymentProvider seam), CallRail, Calendly, QuickBooks. The Zapier path already works today
via the shipped signed generic webhooks.

### B9. Single API keys that flip fixtures to live (no register rows, instant honesty upgrades)
| Key | What goes live |
| --- | --- |
| SerpApi | real rank tracking, map-pack positions, Google AI-Overview visibility, ads-transparency panel |
| PageSpeed Insights | real CWV field data — and once production has CrUX-qualifying traffic, closes WEB-039/044 |
| OpenAI / Gemini / Perplexity | live AI visibility checks + the AI gateway features |
| Ahrefs-class link index | real backlink audits and competitor link tracking |
| Clearbit-class enrichment | real contact/company enrichment and reverse-IP visitor ID |

---

## Track C — Services & people

### C1. Designer engagement — unlocks BRAND-019/023/024/026/027 (module → 100%) · days–weeks
Logo refinement, graphic style, iconography, social and presentation kits. The platform
already stores the deliverables (brand asset store) and publishes the style-guide PDF from
them — the register honestly reserves the *creative production* for a designer.

### C2. Cross-browser matrix run — unlocks WEB-006 · ~2 hours
One BrowserStack/LambdaTest session against a few published tenant pages (risk is low by
construction — no-JS, broadly-supported CSS). Screenshot evidence closes the row.

### C3. Behavior analytics provider — unlocks WEB-036, WEB-054 · ~30 min + Claude
**Microsoft Clarity is free**: create the project, hand Claude the snippet id, and the
wiring into the public-page template is a small prepared change. Heatmaps, session
recordings and scroll maps arrive with real traffic.

### C4. CDN / image pipeline — unlocks WEB-040, supports WEB-046/049/050 · ~2 hours
Cloudflare (free tier) in front of piotrack.com gives the CDN path, image transforms when
needed, and uptime/performance monitoring that the ops rows lean on.

### C5. Define the maintenance routine — unlocks WEB-046, WEB-047, WEB-049, WEB-050 · ~1 hour
These are operational commitments, not features: enable `unattended-upgrades` on the
server, keep the A4 monitor watching, note the cadence (the platform's own daily publish
audits already run). A short written routine in the runbook + the A4/C4 pieces closes them
as honestly operated.

---

## Track D — Your authoring

### D1. Publish one original-research piece — unlocks LLMO-015 (module → 100%) · your time
The platform can do the heavy lifting: the Research Story builder (Outreach page) compiles
your own first-party aggregates with a provenance line, behind a data floor. Turn one such
story into a published content piece with real figures — the LLMO scorer's facts factor
rewards it the moment it exists. The *original data* must be yours; that is the whole point
of the row.

---

## Track E — Claude's prepared code halves (just say the word)

| Trigger | Claude then closes |
| --- | --- |
| Redis installed (A3) | Horizon wiring → JOBS-004, Background Jobs 100% |
| Staging host exists (A6) | CI staging deploy + smoke gate → DEVX-004 |
| YouTube API enabled (B4) | Upload connector → POD-008, Podcast 100% |
| PSI key (B9) | Live WebVitals driver → real field data on Search Health |
| Clarity snippet id (C3) | Public-page wiring → WEB-036/054 |
| No trigger needed | **API reference docs → DEVX-008** — closeable on request today |

---

**Fastest path to the next milestones:** A2 (one afternoon) + A3 + A5 + B4 + D1 close five
more modules → 70/75. The full checklist lands the register at 100%.
