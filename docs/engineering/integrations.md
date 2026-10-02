# Third-party integrations

_Last reviewed: 2026-10-02_

Every outside service has exactly **one client class** in `app/Services/Integrations/`. Only that class talks to the service over HTTP. The business services call the client and own the rules. Add a new client rather than calling a vendor API from a service, Livewire component or job (rule in `.ai/rules/app.md`).

| Service | Client | Used by | Settings |
| :--- | :--- | :--- | :--- |
| DOKU Jokul (payments, refunds, payouts) | `DokuClient` | `DokuPaymentService`, `WalletService` | `DOKU_MODE`; client id and secret key per mode (secrets) in `config/doku.php`; endpoints fixed per mode |
| Laravel Cloud API (custom domains) | `LaravelCloudClient` | `CustomDomainService` via `LaravelCloudDomainProvider` | `LARAVEL_CLOUD_API_TOKEN`, `LARAVEL_CLOUD_ENVIRONMENT_ID` |
| Google Places (Agency Google listing) | `GooglePlacesClient` | `GooglePlacesService` | `GOOGLE_PLACES_API_KEY` |
| Slack (operator activity alerts) | `SlackClient` | `OperatorActivitySlackNotifier` | Admin → Settings webhook, or `SLACK_OPERATOR_WEBHOOK_URL` |
| Cloudflare R2 (media) | Laravel `r2` disk (S3 driver) | `MediaStore` | `MEDIA_DISK=r2`, `R2_*` |
| Mail | Laravel mailer | queued mailables in `app/Mail` | `MAIL_*` |

WhatsApp is not an API integration: `WhatsAppDispatchService` only builds `wa.me` links. Google Calendar is an iCal feed served by the app (`GoogleCalendarService`), not an API call.

## Secrets
Every key, token and password in the table above is a secret: set it in Laravel Cloud (or your own local `.env`), read it only through its config file, and never give it a default or copy it into the database. DOKU credentials used to be copied into `platform_settings` on first boot; that copy and the unused DOKU SNAP RSA key settings are gone.

## DOKU (`DokuClient`)
- **What it does:** handles credentials and the active mode, HMAC-SHA256 request signing, webhook signature checks, hosted checkout (`POST /checkout/v1/payment`), status (`GET /orders/v1/status/{invoice}`), refunds (`POST /orders/v1/refund`), BI-FAST transfers (`POST /disbursement/v1/transfer`), bank codes and phone formatting.
- **Mode:** `DokuClient::mode()` reads `DOKU_MODE`. Credentials are read only by `DokuClient::credentials()`.
- **Demo shop:** the demo operator always uses sandbox and never sends real payouts or refunds.
- **Failures fail closed:** when credentials exist, a refused refund or payout is never treated as done. The offline simulator only runs where it is allowed (local and testing by default, or `DOKU_SIMULATOR_ENABLED`).
- **Webhook:** `POST /api/v1/payments/doku/notify` is CSRF-exempt and must pass `verifyNotificationSignature()`.
- **Open with DOKU before go-live:** see "Payment Gateway Engine" in [`../product/scope-and-features.md`](../product/scope-and-features.md). The refund and payout products enabled on the merchant account must be confirmed with the account manager.

## Laravel Cloud (`LaravelCloudClient`)
- **Endpoints:** `POST /environments/{id}/domains`, `GET /domains/{id}`, `POST /domains/{id}/verify`, `DELETE /domains/{id}` and `GET /applications?include=environments`.
- **Setup check:** `php artisan cloud:environments` checks the token and lists environment ids, so you can copy the production id into `LARAVEL_CLOUD_ENVIRONMENT_ID`.
- **Errors:** a 422 from Cloud (bad or already-used name) reaches the operator as a form error. Any other failure keeps nothing half-connected, and the operator sees "try again in a few minutes".
- Flow details are in [`../features/custom-domains.md`](../features/custom-domains.md).

## Google Places (`GooglePlacesClient`)
- **Endpoints:** Places API (New) place details and text search. Results are cached on the operator as a snapshot and refreshed daily (`google:refresh-reviews`).
- **Pasted links:** a "Maps link" an operator pastes is only followed when it is an https Google Maps host, and every redirect is checked too. Any other URL is never fetched, which prevents server-side request forgery.

## Slack (`SlackClient`)
- Posts only to `https://hooks.slack.com/...`. Any other URL is refused without a request.
- Retries brief network errors and never throws into the caller.

## Cloudflare R2 (`r2` disk)
- **Package:** needs `league/flysystem-aws-s3-v3` (installed).
- **No ACL:** R2 has no object ACLs, so the disk has no `visibility` and `MediaStore` only sets visibility on local disks. Public URLs come from `R2_URL`, the bucket's public domain.
- **Errors throw:** `throw` is on, so a failed upload is never saved as a broken image path.
- **Endpoint:** `R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com`, `R2_REGION=auto`.

## Adding a new integration
1. Create `app/Services/Integrations/{Vendor}Client.php`. Keep it to HTTP, auth and payload shapes only, with timeouts on every call.
2. Put settings in `config/services.php` (never `env()` outside config).
3. Put the rules in a business service that receives the client through its constructor.
4. Test with `Http::fake()`, and assert no request is sent when the integration is not configured.
5. Add a row to the table above.
