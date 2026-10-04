---
paths:
  - 'app/**/*.php'
---

# App

## No emoji in mail subjects or guest messages
Mail subjects, WhatsApp templates, Google Calendar event text, and dashboard announcement titles must not use emoji.

## One client class per third-party service
Every outside API has exactly one client in app/Services/Integrations (DokuClient, SlackClient, GooglePlacesClient, LaravelCloudClient). Only clients call Http:: for that service; business services (DokuPaymentService, OperatorActivitySlackNotifier, GooglePlacesService, CustomDomainService) call the client. Add a new client rather than calling a vendor API from a service, component or job.

## Operator website addresses go through CustomDomainService
Connect, check and disconnect Agency custom domains only with CustomDomainService. The provider is bound from config('domains.provider'): laravel_cloud (production default, Cloud API) or local (development and tests; refuses production). Do not write operator_domains rows for custom domains directly. domains:check is the scheduled re-check.

## Media writes never send an ACL to object storage
MediaStore only sets visibility on local disks. Cloudflare R2 has no ACLs and serves public files from the bucket's public domain (R2_URL). Keep the r2 disk without a visibility key and with throw on.

## No env() outside config
Production on Laravel Cloud caches config, so env() returns nothing outside config/ files (seeders and bootstrap included). Read settings through config(). Trusted proxies come from config('app.trusted_proxies') (TRUSTED_PROXIES, * on Cloud) and are applied in AppServiceProvider.

## Secrets live in Laravel Cloud, read through config
Keys, passwords and tokens are environment variables set in Laravel Cloud (or a developer's own git-ignored .env). Read them only through config/*.php with no default value; never hardcode them in config or code, and never copy them into the database (platform_settings). .env.example keeps a GENERAL block and an empty SECRETS block. SecretsHygieneTest enforces this.

## Shared security helpers (use them, do not re-implement)
- Auth handoff links (login and registration onto the slug desk): only AuthHandoffService (signed, 5 minutes, host-bound, single-use nonce).
- CSV downloads: write every row with CsvExportService::writeRow so guest text cannot run as a spreadsheet formula.
- Errors shown in the UI: ShowsSafeErrors::safeErrorMessage (validation and capacity text only; everything else is reported and replaced). Never put $e->getMessage() of a gateway or database error on screen.
- Operator-typed links and tracking IDs: Operator::isSafeExternalUrl / getSocialLinks (https only) and Operator::TRACKING_ID_PATTERNS, both on save and when printing.
- Never log guest emails, phones or full DOKU response bodies; log the reservation code and DokuClient::errorSummary.

## Booking money rules live in the booking services
- Advance booking: Bookable::earliestBookableDate() is the one rule (date picker, booking box, ReservationBookingService::createHold). Operator desk links pass enforceAdvanceBooking: false but still refuse past dates.
- Checkout only for approved, non-demo shops (Operator::assertCheckoutAllowed).
- Guest coupons are counted when the booking is paid (ReservationLifecycleService), not when the hold is made.
- If the payment page cannot be opened, createHold releases the hold and throws a `payment` validation error.
- Capacity checks that must be exact run inside CapacityService::reserve, or call lockActivities() and assertCanAccommodate(locking: true) inside the transaction (MySQL REPEATABLE READ would otherwise read a stale snapshot).
- A payment for a booking that is already paid, or whose hold ran out while its seats were resold, is refunded, never credited.
- Rupiah amounts (fee, discount, total) are whole numbers.

## PlatformSetting::current() is loaded once per request or job
It is a scoped container binding, cleared on save. Change settings through the model (update/save) so the cache is cleared; do not cache settings anywhere else.
