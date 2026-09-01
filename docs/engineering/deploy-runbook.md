# Deploy runbook — self-hosted production (DEVX-005)

> Production is the self-hosted Ubuntu server (Apache + mod_php 8.5 + PostgreSQL),
> publicly served at https://piotrack.com:8443. Releases are tarballs built on the
> dev machine and applied by `scripts/release.sh` — versioned, health-checked,
> reversible. (The earlier bare `curl && tar` chains failed silently on directory
> permissions; release.sh runs as root end to end and cannot.)

## Build a release (dev machine)

Tarballs contain repo-relative paths: changed `app/ routes/ resources/ database/
config/ public/build docs/` files plus `public/build` whenever front-end changed.
Serve over LAN: `python -m http.server 8000 --bind 0.0.0.0` from the serve
directory. **The dev machine's LAN IP is DHCP-assigned — verify it (`ipconfig`)
before writing the URL.**

## Apply a release (server)

```bash
cd /tmp && curl -fsSO http://<dev-ip>:8000/release.sh && sudo bash /tmp/release.sh http://<dev-ip>:8000/<module>.tar.gz
```

What it does, in order: download to `/var/backups/piotrack-releases/` →
snapshot every file the tarball will overwrite → extract → `migrate --force`
→ `config:cache` + `route:clear` → full Apache restart (opcache + PCRE-JIT
safety) → health-check `GET /health`. **A non-200 health check restores the
snapshot automatically.** Every deploy, failure and rollback is appended to
`/var/backups/piotrack-releases/history.log` with a timestamp and tarball
sha256 — that is the version trail.

## Roll back / inspect

```bash
sudo bash /tmp/release.sh --rollback    # restore the newest pre-release snapshot
sudo bash /tmp/release.sh --history     # list recorded releases
```

Rollback restores files only — a release whose migration must be undone needs
`php artisan migrate:rollback` judged case by case (migrations here are
additive by convention, so file rollback normally suffices).

## Known environment facts

- artisan on the server: `sudo -u www-data HOME=/tmp php artisan …`
- Maintenance window is NOT part of release.sh: additive migrations deploy live.
  For destructive migrations wrap manually: `artisan down --retry=15; … ; artisan up`.
- PCRE JIT must stay off (`/etc/php/8.5/apache2/conf.d/99-pcre-jit-off.ini`);
  a full `systemctl restart apache2` (not reload) after PHP changes.
- Staging does not exist yet (DEVX-003) — releases go dev → production.
