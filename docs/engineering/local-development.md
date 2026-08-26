# Local Development

## Toolchain (this machine)

| Tool       | Location / version                                                |
| ---------- | ----------------------------------------------------------------- |
| PHP        | `C:\tools\php84` (8.4.x, all required extensions incl. pdo_pgsql) |
| Composer   | `C:\tools\composer\composer.phar`                                 |
| Node / npm | on PATH (Node 22+/24)                                             |

Git Bash session setup:

```bash
export PATH="/c/tools/php84:$PATH"
alias composer='php /c/tools/composer/composer.phar'
```

## First run

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build        # or: npm run dev (Vite dev server)
php artisan serve    # http://localhost:8000
```

## Databases

- **Local default: sqlite** (`database/database.sqlite`) — zero-setup; tests run on sqlite `:memory:`
  via `phpunit.xml`.
- **PostgreSQL 16 is the product database.** CI validates every migration and the full test suite
  against Postgres. When Docker Desktop is available locally: `docker compose up -d` starts
  postgres (5432), redis (6379), mailpit (8025 UI), meilisearch (7700); point `.env` at
  `DB_CONNECTION=pgsql`.
- Migrations must stay Postgres-compatible — avoid sqlite-only shortcuts; CI is the referee.

## Quality gate (run before every commit)

```bash
vendor/bin/pint --test          # code style (fix: vendor/bin/pint)
php vendor/bin/phpstan analyse  # static analysis, level 6
npm run format:check            # prettier (fix: npm run format)
npx eslint .                    # lint (fix: npm run lint)
npm run types                   # TypeScript
php vendor/bin/pest             # test suite
```

CI (.github/workflows/ci.yml) runs the same gates plus migration validation on PostgreSQL and
dependency security audits. A red gate blocks merging.

## Conventions

- Health endpoints: `/up` (liveness), `/health` (dependency checks + version).
- Every request carries an `X-Request-Id` (client-supplied or generated); it is attached to all
  log context and returned in the response — quote it when investigating errors.
- Structured JSON logging channel `structured` is used in staging/production via
  `LOG_STACK=structured`.
