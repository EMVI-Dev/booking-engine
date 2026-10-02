# Production launch checklist (Laravel Cloud, fresh database)

_Last reviewed: 2026-10-02_

Use this for the first production deploy, and again after any change to hosting. Hosting background is in [`stack-and-hosting.md`](stack-and-hosting.md). Service details are in [`integrations.md`](integrations.md).

## 1. Laravel Cloud environment
- [ ] **PHP:** 8.5 runtime.
- [ ] **Database:** MySQL resource attached. Cloud injects the `DB_*` variables.
- [ ] **Cache:** database cache is enough. A Cloud cache works too. `onOneServer()` needs a shared store.
- [ ] **Worker:** a queue worker on the app or a worker cluster (`QUEUE_CONNECTION=database`).
- [ ] **Scheduler:** enabled.
- [ ] **Build command:** `composer install --no-dev && npm ci && npm run build && php artisan optimize`
- [ ] **Deploy command:** `php artisan migrate --force`
- [ ] **Domains:** `travelengine.id` and `*.travelengine.id` added with pre-verification. Add `www` too if it is used.
- [ ] **DNS:** records from Cloud are added at the DNS provider. Keep the `_acme-challenge` CNAME permanently; on Cloudflare it must be DNS only.
- [ ] **GitHub connected:** the repository is linked to the environment, so pushes deploy through Cloud (pull request, then deploy).

## 2. Environment variables (Cloud → environment → variables)

| Variable | Value |
| :--- | :--- |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://travelengine.id` |
| `APP_KEY` | **secret**; generated once, never changed after launch |
| `APP_TIMEZONE` | `Asia/Makassar` |
| `TRUSTED_PROXIES` | `*` |
| `MAIL_*` | real mailer (`MAIL_USERNAME` / `MAIL_PASSWORD` **secret**); `MAIL_FROM_ADDRESS="no-reply@travelengine.id"` (SPF and DKIM set for the domain) |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | first platform admin; the password is **secret** (seeding refuses to run without it) |
| `DEMO_OPERATOR_EMAIL` / `DEMO_OPERATOR_PASSWORD` | the public demo shop login; the password is **secret** |
| `REGISTRATION_ENABLED` / `PLATFORM_MAINTENANCE` | open sign-up when ready / `false` |
| `MEDIA_DISK` | `r2` |
| `R2_ACCESS_KEY_ID` / `R2_SECRET_ACCESS_KEY` | **secret**; R2 API token with read and write access to the bucket |
| `R2_BUCKET` | bucket name |
| `R2_ENDPOINT` | `https://<account-id>.r2.cloudflarestorage.com` |
| `R2_URL` | `https://storage.travelengine.id` (bucket public domain) |
| `CUSTOM_DOMAIN_PROVIDER` | leave empty (production defaults to `laravel_cloud`) |
| `LARAVEL_CLOUD_API_TOKEN` | **secret**; Cloud API token |
| `LARAVEL_CLOUD_ENVIRONMENT_ID` | production environment id (get it from `php artisan cloud:environments`) |
| `DOKU_MODE` | `sandbox` until the live checklist below is done, then `live` |
| `DOKU_LIVE_CLIENT_ID` / `DOKU_LIVE_SECRET_KEY` | **secret**; live merchant credentials |
| `DOKU_SIMULATOR_ENABLED` | `false` |
| `GOOGLE_PLACES_API_KEY` | **secret**; server key restricted to the Places API |
| `SLACK_OPERATOR_WEBHOOK_URL` | **secret**; optional; a `https://hooks.slack.com/...` URL |

Secrets (marked **secret** below) go in Cloud's environment variables or Secrets Manager, never in a committed file. `.env.example` lists the same keys in its GENERAL and SECRETS blocks.

## 3. First deploy
1. Merge to the production branch so Cloud deploys. The deploy command runs `migrate --force` on the empty database, with one create migration per table.
2. Run `php artisan db:seed --force` from Cloud **Commands**. This creates the plans, the platform admin and the demo shop. It runs once only.
3. Run `php artisan cloud:environments` and confirm the token works and the environment id matches.
4. Log in at `https://travelengine.id/admin/login` and turn on 2FA for the admin.

## 4. Smoke test
- [ ] `https://travelengine.id` loads over HTTPS, and sign-up sends a verification email.
- [ ] A new operator lands on `https://{slug}.travelengine.id/dashboard` after verifying.
- [ ] Logo and photo uploads appear from `storage.travelengine.id` and survive a redeploy.
- [ ] A sandbox booking pays, the DOKU webhook confirms it, and the guest and operator emails arrive.
- [ ] An Agency test shop connects `tours.<your test domain>`. The records shown match Cloud, and after DNS the address goes live with a padlock.
- [ ] `/up` returns 200. The scheduler and worker show runs in Cloud.

## 5. Before switching DOKU to live
- [ ] Confirm with the DOKU account manager which refund and payout products are enabled on the merchant account. See "Payment Gateway Engine" in [`../product/scope-and-features.md`](../product/scope-and-features.md).
- [ ] Set the live webhook URL to `https://travelengine.id/api/v1/payments/doku/notify`.
- [ ] Run one small live payment, then refund it, end to end.
