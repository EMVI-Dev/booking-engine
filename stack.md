# TravelEngine stack

TravelEngine is a Laravel booking app for tour operators. Guests book on the operator’s own site. Operators and platform admin use the main domain.

Product rules live in `scope_and_features.md`. This file is how the software is built and hosted.

## What it is

| Layer | Choice |
| :--- | :--- |
| Language | PHP 8.5 in production (Herd locally; Composer allows `^8.3`) |
| App | Laravel 13, Blade, Livewire 4 (single-file pages with `⚡`), Blaze |
| Auth | Laravel Fortify (operator log in / register / 2FA / passkeys). Separate Livewire admin log in at `/admin` |
| CSS / JS | Tailwind CSS 4, Vite 8, Alpine.js, Font Awesome |
| Payments | One EMVI DOKU (Jokul) merchant for every plan. No operator-owned gateway |
| Tests | Pest 5, Laravel Pint |
| Local | Laravel Herd |

There is no React/Vue SPA. Operator UI is Livewire. Guest storefront is mostly Blade plus a Livewire booking box.

## Three hosts, one app

The same Laravel app answers on three kinds of hostname:

1. **Platform** — `travelengine.online` and `www` (and local Herd hosts such as `booking.emvi`). Marketing site, operator portal, Fortify auth, platform admin.
2. **Operator slug** — `{slug}.travelengine.online`. Guest storefront only.
3. **Custom domain** — Agency plan, e.g. `yourbrand.com`. Guest storefront only.

`IdentifyOperatorDomain` looks at `Host` and binds the operator (or leaves platform mode). Admin routes are meant to run only on the platform host, not on slugs.

`REGISTRATION_ENABLED=false` keeps the marketing site up but closes operator log in and sign-up (coming-soon page). `/admin/login` still works. Tests force this flag on.

## Request path (production)

```
Guest / operator
    │
    ├─ travelengine.online / www     → Cloudflare (orange-cloud) → Lightsail
    └─ {slug}.travelengine.online    → DNS only (grey) → Lightsail
       custom domain                 → DNS only → Lightsail

Lightsail  18.141.202.28   app in /var/www/travelengine

    Caddy  :80 / :443
        │    reverse_proxy
        ▼
    Nginx  127.0.0.1:8080
        │    PHP-FPM
        ▼
    PHP 8.5  Laravel
```

Supervisor runs `travelengine-worker` and `travelengine-scheduler` (queue + schedule). Default queue driver is `database`.

## TLS

- **Apex + www** — visitors see Cloudflare’s padlock. Origin talks to Cloudflare on 443.
- **Slugs and custom domains** — browsers hit Lightsail directly. Caddy asks Laravel `GET /internal/caddy/ask` before Let’s Encrypt will issue a cert (`CADDY_ASK_TOKEN`). Allowed: real operator slugs, Agency custom domains, and the platform apex/www so origin TLS stays valid.
- Do not put a Cloudflare Origin certificate that includes `*.travelengine.online` in Caddy. That cert is only trusted by Cloudflare, so grey-cloud slugs look “Not secure”.

First HTTPS visit to a new slug can take a few seconds while Let’s Encrypt runs.

## Local vs production data

| | Local | Production | Tests |
| :--- | :--- | :--- | :--- |
| HTTP | Herd | Caddy → Nginx → FPM | Pest HTTP kernel |
| DB | Herd MySQL (typical) | MySQL on the box | SQLite in memory |
| Queue | sync / database | Supervisor + database | `sync` |
| Front-end | `npm run dev` or `composer run dev` | Vite `public/build` uploaded on deploy | n/a |

`public/build` is gitignored. A deploy that skips the Vite build ships a site with no CSS/JS.

## Deploy

GitHub Actions (`.github/workflows/deploy.yml`) on `master` / `main`:

1. `npm ci` + `npm run build` on the runner
2. `rsync` the tree to `/var/www/travelengine` (keeps `.env` and `storage/app`)
3. `composer install --no-dev`, `php artisan migrate --force`, `php artisan optimize`
4. Restart Supervisor

Do not SSH, deploy, or change production without a yes (Lightsail `ubuntu@18.141.202.28`).

Repo: `https://github.com/EMVI-Dev/booking-engine.git`

## App layout (where to look)

| Area | Path |
| :--- | :--- |
| Guest storefront | `app/Http/Controllers/StorefrontController.php`, `resources/views/storefront/` |
| Operator pages | `resources/views/pages/` Livewire SFCs, `routes/web.php` under `auth` |
| Platform admin | `resources/views/pages/admin/`, `/admin/*` |
| Auth | `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/` |
| Domains / TLS ask | `app/Services/DomainResolverService.php`, `app/Http/Controllers/CaddyAskController.php` |
| DOKU | `app/Services/DokuPaymentService.php` |

Primary keys are ULIDs. Guest reservation URLs use `public_token`, never the row id.

## What we do not use

- No Flux UI
- No operator BYO payment gateway
- No Redis/Horizon required for V1
- Enterprise plan is not in the product (`v2.md` only)
- Spatie Permission / Media Library are not installed
