# Installment contract transfer platform (Egypt)

Bilingual (Arabic first, RTL; English second) marketplace for transferring installment real estate contracts.
Site name: لقطة (Arabic) and Lokta (English), domain loktaeg.com. Internal identifiers keep "lo2ta" (repository,
packages, local databases).

## Layout

| Folder | What | Stack |
|---|---|---|
| `core/` | Every table, rule and write; the public API (`/api/v1`); the admin dashboard; mail; sitemaps | Laravel 13, PHP 8.3+, MariaDB 11.8, Inertia 3 + React 19 + TypeScript 6 + Tailwind 4 |
| `web/` | Public site, presentation only | Next.js 16 (App Router), React 19, next-intl 4, Tailwind 4 |
| `docs/` | Plan, research notes, records | Markdown |
| `scripts/` | Repository-wide checks (`check-rtl.mjs`) | Node |

## Local setup (PowerShell)

Prerequisites: PHP 8.3+, Composer 2, Node 24, pnpm 11, MariaDB 11.8 (local service on port 3307).

```powershell
# Databases (scratch only)
& "C:\mariadb-11.8.9\bin\mariadb.exe" -u root -P 3307 -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS lo2ta_scratch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS lo2ta_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Core
Set-Location "D:\coding\l2ta website\core"
composer install
Copy-Item .env.example .env   # only if .env does not exist yet
php artisan key:generate
php artisan migrate
pnpm install
pnpm build

# Web
Set-Location "D:\coding\l2ta website\web"
pnpm install
Copy-Item .env.example .env.local   # only if .env.local does not exist yet
```

## Checks

```powershell
# Core: Pint, PHPStan level 10, Pest (one suite at a time)
Set-Location "D:\coding\l2ta website\core"
vendor\bin\pint --test
vendor\bin\phpstan analyse --memory-limit=1G
vendor\bin\pest
pnpm typecheck; pnpm lint; pnpm lint:rtl

# Web
Set-Location "D:\coding\l2ta website\web"
pnpm typecheck; pnpm lint; pnpm lint:rtl
```

The test suite refuses to run unless mail uses the `array` mailer and the database name ends in `_test`.

## Rules that shape the code

- Arabic is the default language. The URL decides the language (no cookie, no `Accept-Language` redirect).
- Layout uses logical CSS only (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`); `pnpm lint:rtl` fails on physical sides.
- Line endings are LF everywhere except PowerShell and cmd scripts (see `.gitattributes`).
- Secrets live only in `.env` / `.env.local` and on the server; `.env.example` files hold names, never values.
