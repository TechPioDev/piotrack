# Deploying a change to the internal server

The app runs at `/var/www/piotrack` on `192.168.1.230`, served by Apache on `:8080`
and on `:443` for `piotrack.com`. There is no CI deploy; changes are copied over by
hand. This is what that takes, and the three things that reliably go wrong.

## The procedure

Build first if any front-end file changed, then package only what moved:

```bash
npm run build
tar -czf change.tar.gz app/... public/build
```

Serve it from the development machine and pull it from the server:

```bash
# on the dev machine (192.168.1.183)
cd <dir containing the tarball> && python -m http.server 8000 --bind 0.0.0.0
```

```bash
# on the server
cd /var/www/piotrack && rm -f change.tar.gz \
  && curl -fsO http://192.168.1.183:8000/change.tar.gz \
  && sudo tar -xzf change.tar.gz --overwrite \
  && rm change.tar.gz \
  && sudo chown -R www-data:www-data <the paths you extracted> \
  && sudo -u www-data php artisan config:cache
```

## The three things that go wrong

### 1. The file never arrives, and it looks like a partial extraction

Three deploys in a row appeared to half-apply — the PHP landed but the assets did
not, or the reverse. In every case the server had never downloaded the tarball at
all: the `curl` failed, the `&&` chain stopped, and nothing ran.

**Check the file server's access log before diagnosing anything else.** It records
every request, so one glance says whether `192.168.1.230` fetched the file. Hours
went into inspecting build hashes and shared props for something the log answered
immediately.

### 2. `tar` silently skips files it cannot replace

GNU tar unlinks a file before recreating it. When the directory is not writable by
the extracting user it cannot, so it reports `Permission denied` or `File exists`
per entry — and with many entries the errors scroll past. Always extract with
`sudo` and `--overwrite`.

### 3. `config:cache` breaks live requests while it writes

This is the one that wasted the most time, because it presents as an intermittent,
unattributable fault.

`php artisan config:cache` writes `bootstrap/cache/config.php` in place and not
atomically. A request that reads the file mid-write gets truncated PHP, fails to
boot the container, and dies in the shutdown handler with:

```
PHP Fatal error: Uncaught RuntimeException: A facade root has not been set
Target class [view] does not exist
```

The response is a **500 with an empty body**, so there is no error page and nothing
in Laravel's own log. In a browser mid-session Inertia cannot parse it and renders a
**blank white overlay with no message**, which looks like a broken page rather than
a failed request. It clears by itself the moment the write finishes, which is why
retrying always "fixes" it.

Observed rate during a deploy: roughly 3 of 86 requests. Zero on the same routes a
minute later.

**Take the app down for the few seconds it takes**, so the failure is a clear
maintenance page rather than random blank screens:

```bash
cd /var/www/piotrack \
  && sudo -u www-data php artisan down --retry=15 \
  && sudo -u www-data php artisan config:cache \
  && sudo -u www-data php artisan view:clear \
  && sudo -u www-data php artisan up
```

`artisan down` is not needed for a build-only change — static assets are replaced
file by file and served directly by Apache.

## Verifying

Check the thing that changed, not that the site is up — the site is almost always
up. For a front-end change compare the manifest:

```bash
curl -s http://192.168.1.230:8080/build/manifest.json | grep -o '"assets/app-[^"]*"'
```

against the local one. For a back-end change find something observable: a response
header, a shared Inertia prop, a redirect target. If nothing is observable from
outside, say so rather than reporting the deploy as verified.

## Running artisan on this server

Always as `www-data`, and with a writable HOME:

```bash
sudo -u www-data HOME=/tmp php artisan <command>
```

As the login user, artisan cannot read `.env`, silently falls back to framework
defaults — including `DB_CONNECTION=sqlite` — and reports a missing
`database.sqlite` on a PostgreSQL install. `psysh`, which backs `tinker`, also needs
a writable home directory.
