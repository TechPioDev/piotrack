#!/usr/bin/env bash
#
# piotrack — versioned release deploy with health check and rollback (DEVX-005).
#
#   sudo bash release.sh <tarball-url>     # deploy a release
#   sudo bash release.sh --rollback        # restore the previous release
#   sudo bash release.sh --history         # list recorded releases
#
# The tarball is produced by the dev machine (repo-relative paths). Before
# extracting, every file the tarball will overwrite is snapshotted, so a bad
# release is reversible with --rollback. After extraction the script migrates,
# rebuilds caches, restarts Apache, and health-checks /health — a failing
# health check triggers an automatic rollback. Every action appends to the
# release history log. Runs entirely as root: no silent permission failures.

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/piotrack}"
RELEASES_DIR="${RELEASES_DIR:-/var/backups/piotrack-releases}"
HEALTH_URL="${HEALTH_URL:-https://127.0.0.1:5050/health}"
HISTORY="$RELEASES_DIR/history.log"

fail() { echo "!!!!! FAILED: $*" >&2; exit 1; }
note() { echo "===== $*"; }

[[ $EUID -eq 0 ]] || fail "run with sudo"
mkdir -p "$RELEASES_DIR"
cd "$APP_DIR" || fail "cd $APP_DIR"

artisan() { sudo -u www-data HOME=/tmp php artisan "$@"; }

health_check() {
    local code
    code=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 20 "$HEALTH_URL" || echo 000)
    echo "$code"
}

finish_release() {
    artisan migrate --force || return 1
    artisan config:cache || return 1
    artisan route:clear || return 1
    systemctl restart apache2 || return 1
    sleep 2
    [[ "$(health_check)" == "200" ]]
}

if [[ "${1:-}" == "--history" ]]; then
    cat "$HISTORY" 2>/dev/null || echo "no releases recorded"
    exit 0
fi

if [[ "${1:-}" == "--rollback" ]]; then
    SNAP=$(ls -1t "$RELEASES_DIR"/pre-*.tar.gz 2>/dev/null | head -1)
    [[ -n "$SNAP" ]] || fail "no snapshot to roll back to"
    note "rolling back using $SNAP"
    tar -xzf "$SNAP" -C "$APP_DIR" || fail "restore snapshot"
    chown -R www-data:www-data "$APP_DIR"
    finish_release || fail "rollback finished but health check is not 200 — investigate"
    echo "$(date -Is) ROLLBACK $(basename "$SNAP") health=200" >> "$HISTORY"
    note "rollback complete — health 200"
    exit 0
fi

URL="${1:-}"
[[ -n "$URL" ]] || fail "usage: release.sh <tarball-url> | --rollback | --history"

VERSION="$(date +%Y%m%d-%H%M%S)-$(basename "$URL" .tar.gz)"
TARBALL="$RELEASES_DIR/$VERSION.tar.gz"

note "1/5 fetch $URL"
curl -fsS "$URL" -o "$TARBALL" || fail "download"
SHA=$(sha256sum "$TARBALL" | cut -c1-12)

note "2/5 snapshot files this release will overwrite"
SNAP="$RELEASES_DIR/pre-$VERSION.tar.gz"
tar -tzf "$TARBALL" | grep -v '/$' | (cd "$APP_DIR" && tar -czf "$SNAP" --ignore-failed-read -T - 2>/dev/null) || true
[[ -s "$SNAP" ]] || note "  (nothing to snapshot — all files are new)"

note "3/5 extract"
tar -xzf "$TARBALL" -C "$APP_DIR" --overwrite || fail "extract"
chown -R www-data:www-data "$APP_DIR"

note "4/5 migrate, caches, restart, health check"
if finish_release; then
    echo "$(date -Is) DEPLOY $VERSION sha=$SHA health=200" >> "$HISTORY"
    note "5/5 release $VERSION live — health 200"
else
    note "HEALTH CHECK FAILED — rolling back automatically"
    if [[ -s "$SNAP" ]]; then
        tar -xzf "$SNAP" -C "$APP_DIR"
        chown -R www-data:www-data "$APP_DIR"
        finish_release || true
    fi
    echo "$(date -Is) FAILED $VERSION sha=$SHA (auto-rollback attempted, health=$(health_check))" >> "$HISTORY"
    fail "release $VERSION failed health check; previous files restored (health=$(health_check))"
fi

# Keep the last 10 snapshots + tarballs; prune the rest.
ls -1t "$RELEASES_DIR"/pre-*.tar.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
ls -1t "$RELEASES_DIR"/*.tar.gz 2>/dev/null | grep -v '/pre-' | tail -n +11 | xargs -r rm -f
