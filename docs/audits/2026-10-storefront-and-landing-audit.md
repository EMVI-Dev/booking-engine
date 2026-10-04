# Security, Storefront and Landing Page Audit — October 2026

_Snapshot: 2026-10-03. Follows [2026-10-backend-audit.md](2026-10-backend-audit.md)._

Scope:

- A second security pass over the whole app.
- The guest storefront (booking box, checkout, payment, receipt).
- The platform landing page (`welcome.blade.php` and `PlatformSeoService`).

Backend items were fixed in this pass, with tests. UI and copy items are listed for Antigravity in section 4. Every fix runs green on SQLite and MariaDB.

## 1. Security — fixed

| # | Problem | Fix |
| :--- | :--- | :--- |
| S1 | Error pages could show raw exception text | 4xx pages show a message only for a plain `abort()`. The 500 page is always generic. `ErrorPageLeakTest` |
| S2 | Media delete and copy accepted any path, including another operator's files | `MediaStore::scopedTo($operator)` refuses paths outside `operators/{id}/` and `..`. Existing-photo props are `#[Locked]` |
| S3 | The vendor email link used the guest's `public_token`, so a vendor could open the receipt, pay or cancel | New `reservations.vendor_token`. Route is `/vendor-dispatch/{vendorToken}`, and the guest token gives a 404 there |
| S4 | Several pages checked a role only in the menu | `authorizeAbility()` on mount and on actions: payments, wallet, vendors, storefront, billing, brand, plan checkout, reservation notes and sync, guest export, calendar blackouts. `RoleGatesTest` |
| S5 | Login and registration handoff links could be replayed for 5 minutes, on any host | `AuthHandoffService`: signed, host-bound, single-use nonce |
| S6 | iCal feed token never changed, and the feed sent `Access-Control-Allow-Origin: *` | "Create a new link" action (catalog managers). The token also rotates when a teammate is removed. CORS header dropped, strict token format |
| S7 | No security headers | `AddSecurityHeaders`: `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, and HSTS in production over HTTPS |
| S8 | Social links accepted `javascript:`. Tracking IDs were free text printed inside `<script>` | `url:https` validation. Bare `instagram.com/x` gets `https://`. IDs must match `Operator::TRACKING_ID_PATTERNS` on save and when printed, and are printed with `@js` |
| S9 | The receipt URL (it carries the guest token) went to Google Analytics | GA on the receipt gets `page_location=/booking-confirmed` |
| S10 | CSV formula injection in five exports | `CsvExportService::writeRow` on guests, coupon report, and admin operators, subscriptions and payouts |
| S11 | No rate limit on sign-up, password reset, coupon guessing, hold creation or team invites | `ThrottleAuthForms` (Fortify register and reset posts). The booking box allows 10 coupon tries and 5 holds per minute per visitor. Invites are capped at 20 per hour per shop |
| S12 | Settings pages opened before email verification | Settings need `verified`. The profile page stays open so a mistyped email can be fixed |
| S13 | A DOKU success webhook without an amount was accepted | Webhook successes must state an amount. An amount below the invoice is still refused |
| S14 | Inviting an existing email pulled another shop's owner, or an admin, into this team | Refused. `User::currentOperator()` order is now fixed (first joined) |
| S15 | Raw gateway and database errors were shown in toasts. Guest emails and DOKU bodies were logged | `ShowsSafeErrors` trait. Logs keep the reservation code and the DOKU error code and message only |
| S16 | Seeders fell back to password `password` on staging and preview | The fallback is only for `local` and `testing`. Seeders no longer call `env()` |

**Decided, not changed:**

- **Webhook timestamp and Request-Id replay checks: skipped.** Processing is idempotent: the payment row is locked, and only an open invoice can become paid. A strict timestamp window could drop a real DOKU retry.
- **Meta Pixel on the receipt page: kept.** Meta still sees the receipt URL. It is the operator's own pixel, on a paid feature. Revisit if the cancel link moves behind a second check.

## 2. Storefront — fixed

| # | Problem | Fix |
| :--- | :--- | :--- |
| P0-1 | **Capacity race on MySQL/MariaDB.** A read before the lock fixed the transaction snapshot, so two guests could both get the last seats | Relations are loaded before the transaction, and activities are locked first. Booked seats are read with a locking read, which always sees the latest data. Payment retries and late payments use the same path (`CapacityService::lockActivities`, `assertCanAccommodate(locking: true)`) |
| P0-2 | A payment landing after the hold ran out (before the expiry job) was confirmed without a seat check | Treated like an expired hold: confirmed only if the seats are still free, otherwise refunded |
| P0-2b | The DOKU page stayed open 30 minutes, whatever the hold length | `dueMinutes` = time left on the hold |
| P0-3 | DOKU down gave a 500 and kept the seats blocked | The hold is released, the invoice is marked failed, and the guest sees "Nothing was charged, try again". The pay-again page does the same |
| P0-4 | Unknown slugs and hosts showed the marketing site with status 200 | 404. A custom domain that is no longer live redirects to the same path on the slug address |
| P1 | A second payment on a paid booking was credited twice | Refunded, never credited |
| P1 | Coupon use was counted at hold time, so abandoned checkouts used coupons up | Counted when the booking is paid |
| P1 | Fee and total could have cents. DOKU takes whole rupiah | Rounded to whole rupiah |
| P1 | Advance-booking rule differed between picker, booking box and service, and was not enforced server-side | One rule, `Bookable::earliestBookableDate()`, enforced in `createHold`. Desk booking links skip it but still refuse past dates |
| P1 | Pending and suspended shops could still create pay links | `Operator::assertCheckoutAllowed()` requires an approved shop |
| P2 | `PlatformSetting::current()` ran a query on every call | One load per request or job (scoped binding), reloaded after a save |

## 3. Landing page — backend fixed

| Problem | Fix |
| :--- | :--- |
| `welcome.blade.php` (and, after the redesign, the controller) called `Plan::seedDefaultPlans()`. That is a database write on a page view, and it could reset admin plan edits | Removed. Plans come from the seeder. The controller passes the page data, including `guestFeeSummary` |
| JSON-LD offers had hardcoded prices | Built from `Plan::catalog()` |
| FAQ hardcoded "5%". The payout answer said money is sent after bank settlement with no mention of escrow | The fee comes from settings (`PlatformSeoService::guestFeeSummary()`, e.g. "5% (max Rp 250.000)"). The payout answer now says: held until the trip date, then you request a payout |
| SEO copy promised automatic WhatsApp tickets | Now "E-tickets by email, and one-tap WhatsApp messages to guests" |
| The GA id defaulted to a real property in every environment, and GA ran on the operator desk and admin | `PLATFORM_GOOGLE_ANALYTICS_ID` has no default. GA runs only on the platform root, on marketing and auth pages |
| `featureCatalog` was missing paid features | Added Capacity Heatmap, Marketing Tracking Pixels and Priority support, with the same names as the desk |
| robots `Disallow: /dashboard/` missed `/dashboard` | `/dashboard`, `/admin`, `/settings`, `/auth/` and `/vendor-dispatch/` are blocked |
| `REGISTRATION_ENABLED` did nothing, but `.env.example` said it closed sign-up | Removed. `PLATFORM_MAINTENANCE` is the only switch |

## 4. For Antigravity (UI and copy)

**Landing page (`welcome.blade.php`, after the Oct 3 redesign).** The page has no line numbers here because it is still changing; search for the quoted text.

1. **Instant payout claims are wrong.** Examples: "ONLINE PAYMENTS & BANK PAYOUTS: ACTIVE", the "INSTANT" stat, and "direct bank payouts". Money is held until the trip date, then the operator requests a payout. DOKU settles T+3, so there is no instant or same-day payout.
2. **Automatic WhatsApp tickets are claimed.** Examples: "instant WhatsApp guest tickets" and the "WHATSAPP TICKET" tab. WhatsApp is a one-tap `wa.me` message the operator sends. E-tickets are emailed automatically.
3. **The fee is hardcoded as "5%"** in three places. The controller now passes `$guestFeeSummary` (e.g. "5% (max Rp 250.000)"), which follows Admin → Settings.
4. **Demo numbers look like live data.** The mock tours, seats and "Fee: Rp 0 taken" rows need an "Example" label. Do not use "live" or "verified" wording on them.
5. **Prices in the comparison or pricing header** must come from `$plans`, not typed-in amounts. Starter should read "Free", not "Rp 0 / month".
6. **"Set up in minutes" is overstated.** Payouts also need bank details.
7. **Register buttons during maintenance.** `$registrationOpen` is passed in; hide or disable the buttons when it is false.
8. **Untrusted strings in `x-text`.** Wrap them with `@js()`.
9. **Images.** Fix the og:image size (1200×630) and compress the heavy images.
10. **Font Awesome.** It is loaded in full; load only the icons used.

**Storefront:**

1. **Receipt states** (`storefront/confirmation.blade.php`, about lines 33–79): add clear states for Completed, Declined, Expired and Refunded. Today these fall into generic text.
2. **Payment errors in the booking box** now arrive under `requested_date`. Show them near the pay button.
3. **White-label the error pages** on operator hosts (404, 403 and 503 show platform branding).
4. **Booking box.** Two instances on one page (mobile and desktop) share no state; render one.
5. **Canonical URL.** When an operator has an active custom domain, the slug pages should set the canonical to the custom domain.
6. **Home page lists.** `storefront/index` loads every listing. Paginate it or cap it at about 12.

**Operator desk:**

1. **Sidebar shows pages a role cannot open.** They now return 403. Hide them using `canOperate()`.
2. **iCal modal** has a plain "Create a new link" button. Style it.

## 5. Open decisions (owner)

- Commission fields on plans are unused (operators keep 100%). Remove them, or keep them for V2?
- Local `.env.live`, `private.key` and `public.key` files exist on the Mac. Confirm they are not needed, then delete them.
- The local `.env` holds live DOKU keys. Keep live keys only in Laravel Cloud.
- The local `APP_KEY` is the same as the one in `.env.testing`. Generate a new key for local.
