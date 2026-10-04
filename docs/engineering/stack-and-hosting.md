# Stack and hosting

_Last reviewed: 2026-10-03_

TravelEngine is a Laravel booking app for tour operators, built by EMVI Technologies. Guests book on the operator's own site. Operators and platform admins use the main domain.

Product rules live in [`../product/scope-and-features.md`](../product/scope-and-features.md). This file covers how the software is built and hosted. Third-party services are in [`integrations.md`](integrations.md), and the go-live checklist is in [`production-launch.md`](production-launch.md).

## What it is

| Layer | Choice |
| :--- | :--- |
| Language | PHP 8.5 (Herd locally; Composer allows `^8.3`) |
| App | Laravel 13, Blade, Livewire 4 single-file pages (`⚡`, no Volt), Blaze |
| Auth | Laravel Fortify: operator login, registration, email verification, 2FA, passkeys. Separate Livewire admin login at `/admin/login` |
| CSS / JS | Tailwind CSS 4, Vite, Alpine.js, Font Awesome |
| Database | MySQL / MariaDB in production; SQLite in memory for tests (the suite also passes on MariaDB) |
| Payments | One EMVI DOKU (Jokul) merchant for every plan. No operator-owned gateway |
| Hosting | Laravel Cloud (app, workers, scheduler, database) |
| Media | Cloudflare R2 (S3-compatible), public bucket domain |
| Tests | Pest 5, Laravel Pint, Larastan |

There is no React or Vue SPA. The operator UI is Livewire. The guest storefront is mostly Blade plus a Livewire booking box.

## Three kinds of host, one app

1. **Platform**: `travelengine.id` and `www`, plus local Herd hosts. Serves the marketing site, operator portal, auth and platform admin.
2. **Operator slug**: `{slug}.travelengine.id`. Guest storefront and that operator's desk (login hands off here).
3. **Custom domain**: Agency plan, e.g. `yourbrand.com`. Guest storefront only. See [`../features/custom-domains.md`](../features/custom-domains.md).

`IdentifyOperatorDomain` reads the `Host` header and binds the operator, or leaves platform mode. Admin routes run only on the platform host (`EnsureOnPlatformDomain`, re-checked on every Livewire action). Reserved names such as `admin`, `www`, `api` and `demo` can never be a shop slug (`Operator::RESERVED_SLUGS`).

Every web response carries baseline security headers (`AddSecurityHeaders`: no framing, `nosniff`, a strict referrer policy, and HSTS over HTTPS in production). Fortify's sign-up and password-reset posts are rate limited by `ThrottleAuthForms`, because Fortify cannot limit them through config.

Operator sign-up and storefront booking follow platform maintenance only (Admin → Settings, or `PLATFORM_MAINTENANCE`). This is not Laravel's `down` mode, and there is no separate registration switch.

## Production on Laravel Cloud

```
Guest / operator
    │
    ├─ travelengine.id, www            ┐
    ├─ {slug}.travelengine.id          ├─ Laravel Cloud edge (TLS) → app instances (PHP 8.5, Laravel)
    └─ Agency custom domains           ┘
                                          │
                                          ├─ MySQL (Cloud database)
                                          ├─ queue worker + scheduler (Cloud)
                                          └─ media → Cloudflare R2 (storage.travelengine.id)
```

- **Wildcard domains:** `*.travelengine.id` and `travelengine.id` are attached to the environment. Keep the `_acme-challenge` CNAME in DNS permanently so Cloud can renew the wildcard certificate. If DNS is on Cloudflare, that record must be DNS only.
- **Custom domains:** each one is added to the environment through the Laravel Cloud API (the `laravel_cloud` provider, the default in production) and counts toward the Cloud plan's custom-domain allowance.
- **Filesystem:** the local disk on Cloud is ephemeral. Every upload goes to R2 (`MEDIA_DISK=r2`), and the app never relies on `storage:link` in production.
- **Proxies:** the app is reached only through Cloud's edge, so set `TRUSTED_PROXIES=*` to get the real guest IP and HTTPS. This is read from config, so it works with cached config.
- **Config cache:** Cloud builds run `php artisan optimize`. Never call `env()` outside `config/` files.

### How code reaches production

- **Deploys:** GitHub is connected to Laravel Cloud. Pushing to GitHub triggers Cloud: it opens and builds the pull request, and deploys to the Cloud environment once merged. There is no GitHub Actions deploy workflow.
- **CI:** `.github/workflows/tests.yml` runs the test suite (`composer ci:check`) on pushes to `main` and on pull requests. Run Pint and PHPStan locally before pushing.

### Build and deploy commands (Cloud environment settings)

- **Build:** `composer install --no-dev && npm ci && npm run build && php artisan optimize`
- **Deploy:** `php artisan migrate --force`
- Do not add `queue:restart`, `optimize:clear` or `storage:link`; Cloud handles these or they do not apply.

`public/build` is gitignored, so a build without `npm run build` ships with no CSS or JS.

## Environment and secrets

- `.env` holds **general** per-environment settings only; `.env.example` lists them in two blocks, GENERAL and SECRETS.
- **Secrets** (keys, passwords, tokens) are set in Laravel Cloud (environment variables or Secrets Manager) and, locally, only in your own git-ignored `.env`. They are read through `config/*.php` with no default value, and never stored in a committed file or in the database.
- `tests/Feature/Security/SecretsHygieneTest.php` enforces this: secrets stay empty in `.env.example`, have no config defaults, and never land in `platform_settings`.
- Non-secret tuning (DOKU endpoints, R2 region, WebP quality, session and cache drivers) lives in config with fixed values or sensible defaults, so `.env` stays short.

## Queues, scheduler and time

- **Queue:** the `database` driver, with `after_commit` on, so mail for a booking that rolled back is never sent. Guest, operator, vendor and renewal mails are queued.
- **Scheduler:** defined in `routes/console.php`. Every job runs `withoutOverlapping()->onOneServer()`, which needs a shared database or redis cache. Money and booking jobs:
  - `wallet:release-escrows` runs hourly
  - `reservations:expire-holds` runs every 5 minutes
  - `trips:mark-completed` runs daily at 00:15
  - `subscriptions:return-unpaid-to-free` runs daily at 00:45
  - `platform:match-payments` runs daily at 03:00
- **Custom domains:** `domains:check` runs every 5 minutes.
- **Time zone:** `APP_TIMEZONE=Asia/Makassar` (WITA, UTC+8). "Today", trip days, free-cancellation cutoffs, escrow release and the scheduler all follow Bali time. DOKU request timestamps and iCal stamps are written in UTC explicitly.

## Local vs production vs tests

| | Local | Production | Tests |
| :--- | :--- | :--- | :--- |
| HTTP | Herd | Laravel Cloud | Pest HTTP kernel |
| DB | Herd MySQL | Cloud MySQL | SQLite in memory |
| Queue | sync or database | Cloud worker, database driver | `sync` |
| Media | `public` disk | `r2` disk | faked disk |
| Custom domains | `local` provider (no-op; a check marks it live) | `laravel_cloud` provider | `local`, or Cloud API faked |
| Front end | `npm run dev` / `composer run dev` | built in the Cloud build step | n/a |

## Where to look in the code

| Area | Path |
| :--- | :--- |
| Guest storefront | `app/Http/Controllers/StorefrontController.php`, `resources/views/storefront/` |
| Operator pages | `resources/views/pages/` (Livewire SFCs), `routes/web.php` under `auth` + `verified` |
| Platform admin | `resources/views/pages/admin/`, `/admin/*` |
| Auth | `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/`, `app/Http/Responses/` |
| Business logic | `app/Services/` (one service per job; see `.ai/rules/app.md`) |
| Third-party clients | `app/Services/Integrations/` ([`integrations.md`](integrations.md)) |
| Hosts and domains | `app/Services/DomainResolverService.php`, `app/Services/CustomDomainService.php` |
| Schema | `database/migrations/`: one create migration per table, in its final shape |

Primary keys are ULIDs. Guest reservation URLs use `public_token`, never the row id.

## What we do not use

- No Flux UI and no Volt
- No operator bring-your-own payment gateway
- No Redis or Horizon required for V1
- No Spatie Permission or Media Library
- Enterprise plan is not in the product ([`../product/roadmap-v2.md`](../product/roadmap-v2.md))
