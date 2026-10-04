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

## Google listing: free Google services only
Agency Google listing uses only free Google services: the id-only Place Details check (connect + monthly google:check-listings), the Maps Embed card (GOOGLE_MAPS_EMBED_KEY, public), and plain Maps / write-review links. Store only settings.google_place = {place_id, connected_at}. Never call billed Places requests (name search, share-link lookup, displayName, rating, reviews) and never store Places content. Operators connect by pasting a Place ID.

## One create migration per table (pre-launch)
Production is rebuilt fresh until launch, so schema changes are folded into the table's create migration (or a new create migration for a new table). No Schema::table alter migrations. SchemaBaselineTest enforces this. After real launch with data, switch to additive migrations.

## Storefront website extras go through their services
Gallery: StorefrontGalleryService (MediaStore WebP in operators/{id}/gallery, plan gallery_photo_limit, extra photos hidden not deleted after a downgrade). FAQ: StorefrontFaqService (generated answers + settings.faq hidden/items, FAQPage markup). Contact form: EnquiryService (contact_form plan feature + settings.contact_form opt-in, desk Enquiries list always, email only when enabled, honeypot + min time + rate limit, no CAPTCHA). Tracking scripts are printed inert and only run after cookie consent (partials.cookie-consent).

## Seeding is idempotent and checks storage
DatabaseSeeder skips plans, the admin and the demo shop when they exist (never resets the admin password or Admin → Plans edits). Plans are created with Plan::ensureDefaultPlans() (only when none exist); seedDefaultPlans() is only for the explicit admin reset. DemoOperatorSeeder (and demo:refresh) calls MediaStore::verifyWritable() before wiping or uploading anything.
