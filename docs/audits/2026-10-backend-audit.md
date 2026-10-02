# Backend Audit — October 2026

Scope: `app/**`, `routes/**`, `config/doku.php`, migrations, and the PHP class blocks of every Livewire SFC (`resources/views/**/⚡*.blade.php`, ~9k lines of business logic live there). UI/markup was not reviewed (Antigravity owns it).
Method: read-only code review against `docs/emvi_v1_money_rules_and_settlement_spec.md`. Tests were **not** run (no PHP on the reviewing machine).

Severity: **C** = exploitable or loses money today · **H** = wrong money/state or broken feature · **M** = correctness/robustness · **D** = DRY / structure.

---

## 1. Critical — fix before anything else

| # | Where | Problem | Fix |
|---|---|---|---|
| C1 | `WalletService::createPayoutRequest` (L179), `settings/⚡payments`, `DemoOperatorSeeder`, login page | **Demo desk can drain real money.** Demo credentials are shown on `demo.{platform}/login`; demo owner gets Rp 4.3M *cleared* balance re-seeded daily; bank account is editable; payouts ≤ Rp 10M auto-disburse via `DokuPaymentService::disbursePayout()` which uses `gatewayCredentials()` **without the operator**, i.e. LIVE keys. | Block every money/write path for `isDemo()` (payout request, bank update, refunds, coupons). Pass operator into all gateway calls. Seed demo balance as `PendingEscrow` or not at all. |
| C2 | `livewire/storefront/⚡booking-box` L33, `totalPrice()`, `submitBooking()` | **Guest sets their own discount.** `$discountAmount` is a public Livewire property; `submitBooking()` never re-validates the coupon. `$wire.set('discountAmount', 99999999)` → guest pays only the service fee; snapshot + payment split trust it. | Make coupon state `#[Locked]` and recompute the quote server-side at submit from `appliedCouponCode` (see D1/D2). |
| C3 | `settings/⚡plan` L33/L50, `confirmPlanSwitch()` | **Operator upgrades for free.** Same pattern: `discountAmount` public → `createPendingUpgrade()` sees `netDue <= 0` → `executeUpgrade()` activates paid plan with gateway `wallet_credit`. `target_plan_id` also accepts inactive plans (`Plan::find`). | Re-derive coupon + discount server-side in `confirmPlanSwitch()`; `Plan::where('is_active', true)`; `#[Locked]` on derived money props. Same in `⚡plan-checkout` (L46). |
| C4 | `reservations/⚡show` L27, L152 `executeStatusTransition()` | **No server-side state machine.** `pendingStatusValue` is public; `executeStatusTransition()` skips every check in `confirmStatusTransition()`. Any teammate (incl. *Finance*) can: mark a future trip **Completed → escrow released early → withdraw**; mark an unpaid booking **Confirmed**; **Decline** a paid booking without refund. | `ReservationStatus::canTransitionTo()` + `ReservationLifecycleService` (D3) enforcing transitions, payment state, dates and `authorizeAbility('manageReservations')`. |
| C5 | `DokuPaymentService::processNotification` L686-703 | **Late/replayed `FAILED`/`EXPIRED` webhook un-pays a paid booking.** No status guard, no lock: payment → `Failed`, confirmed reservation → `PaymentPending`, while the wallet credit stays. Webhook has no `Request-Timestamp` freshness check, so a captured signed FAILED notification can be replayed. | Only transition `Pending` payments (locked row); ignore terminal states; reject timestamps older than ~5 min. |
| C6 | `WalletService::approvePayout/rejectPayout` L207/L222, `admin/⚡payouts::disburseViaDokuApi` | **Payout double-spend.** Guards live only in the Livewire component and run without a lock. Double-clicking *Reject* writes two reversal credits; double-clicking *Disburse* sends two bank transfers (fresh random reference each call, no idempotency). `Processing` status exists but is never used. Auto-disburse runs an HTTP call **inside** the DB transaction holding the operator lock. | `PayoutService` with `lockForUpdate`, `Pending → Processing → Completed/Rejected`, stable `partner_reference_no = payout.reference_number`, disburse after commit (queued job). |
| C7 | `User::isAdmin()` L59, `User` (no `MustVerifyEmail`) | **Platform admin by email string, and email is never verified.** `isAdmin()` is true for `admin@travelengine.id` / `admin@emvi.dev`; `User` does not implement `MustVerifyEmail`, so the `verified` middleware is a no-op. If either address has no account yet, anyone can register it and open `/admin`. Team invites can also create users with those emails. | Remove the hard-coded list (use `is_admin` only), implement `MustVerifyEmail`, block those emails at registration/invite. |

## 2. High

| # | Where | Problem |
|---|---|---|
| H1 | `StorefrontController::payReservation` L436 | Revives **Cancelled / Declined / Expired** bookings: only `Confirmed` or a paid latest payment is refused, then status is forced to `PaymentPending`. Operator-declined or refunded bookings can be re-opened and re-paid. Allow only `PaymentPending`. Blackouts are not re-checked here either. |
| H2 | `OperatorUserRole::abilities()` vs components | `manageReservations` and `manageCatalog` are **never enforced** (grep: 0 calls). Finance can cancel/refund, edit products/packages/coupons/vendors. Plan features `promotional_coupons`, `whatsapp_dispatch`, booking-link are UI-only. Plan page auto-renew / cancel-downgrade lack `manageBilling`. |
| H3 | `ExpireStaleHoldsCommand`, webhook SUCCESS path | Expire command loads rows then `update()`s without re-checking status → can overwrite a just-**Confirmed** booking with `Expired`. Conversely a payment that lands after expiry confirms the booking **without a capacity re-check** → overbooking. Use conditional updates (`where status = payment_pending`) and capacity check / auto-refund on late payment. |
| H4 | `SubscriptionProrationService` | (a) Upgrade charges a **prorated** price but resets `plan_expires_at` to now + 1 month/year → undercharge every mid-cycle upgrade. (b) Scheduled downgrade to a *paid* plan grants a new paid period with no invoice. (c) Listing limits are enforced only by `LapsedSubscriptionService`; downgrades/complimentary don't draft extras, and drafted listings can simply be re-published from edit (no `canAddListing` check on publish). |
| H5 | Subscription coupons | Usage is incremented only on simulator/zero-amount paths — **never when DOKU actually confirms** (`completePendingPayment`). `redemption_scope` (`first_purchase_only`, `once_per_period`) and `OperatorCouponRedemption` are never written/checked. `min_monthly_revenue` rule filters `payments.status = 'completed'` (enum value is `paid`) → never matches. |
| H6 | `WalletService` ledger ops | `cancelBookingEarning()` not idempotent (2nd call on a cleared earning writes a 2nd debit) and uses `ManualAdjustment` instead of `RefundDeduction`. `processPartialRefund()` unbounded. Operator cancellation doesn't debit `F_guest + F_gw` per spec Policy 4. Dispute hold = `amount + 150k` from webhook but `amount` only from admin screen. No dispute *lost* finalisation. |
| H7 | `GuestCancellationService`, `reservations/⚡show` cancel | No row lock → concurrent cancel clicks can call the DOKU refund API twice. Refund is fired before state change outside a transaction. |
| H8 | `reservations/⚡show` L98 | Calls `DokuPaymentService::checkPaymentStatus()` — **method does not exist** (it's `syncPaymentStatus()`); the "sync payment" button always shows an error. |
| H9 | `DokuPaymentService` | `signedHeaders()`, `verifyNotificationSignature()`, `queryPaymentStatus()`, `refundPayment()`, `disbursePayout()` ignore the operator → demo sandbox checkout is signed with live keys (and vice-versa). Thread `?Operator` through every call. |
| H10 | `VendorDispatchService::sendTestNotification` | "Send test" creates a **real published Product** (bypasses listing cap) and a **real Confirmed reservation** (consumes capacity, creates a CRM guest, inflates metrics). Vendor dispatch link reuses the guest `public_token`, so a vendor can open/cancel the guest booking. Use an unsaved model + a separate signed URL. |
| H11 | `StorefrontController::lookupBooking` L547 | Contact check is `guest_contact LIKE %digits%` OR exact name → typing "1" matches almost any phone; the booking code is the only real secret, and the e-ticket shows guest PII. Require normalized full phone / email match. |

## 3. Medium

- **M1 Timezone.** `app.timezone = UTC`; all day logic (escrow release `startOfDay`, free-cancel cutoff, advance-booking, `trips:mark-completed`, "today" filters, scheduler times) is 8h off for Bali (UTC+8). Spec says release at 00:05 WIB. Introduce a business timezone (`Asia/Makassar`/per operator) and compute day boundaries in it.
- **M2 Webhook robustness.** Returns 400 for unknown statuses (DOKU retries forever); `throttle:60,1` can drop bursts; guest/operator/vendor mails are sent **synchronously** inside the webhook → use `Mail::queue`.
- **M3 Duplicate invoices.** Every `/pay` visit creates a new `Payment`; a guest paying two open invoices is double-charged and `PaymentMatchService` (reservation-level) won't flag it. Reuse the open pending payment or expire previous ones.
- **M4 Coupon usage** counted at hold creation; expired holds never release it; `used_count` check/increment is not atomic. Percentage coupons have no `max:100`.
- **M5 Ledger integrity.** Deleting an operator cascades `wallet_transactions`/`payments` (spec: append-only). No DB unique on `(reservation_id, type)` for `booking_earning`. Products/packages with upcoming paid bookings can be hard-deleted (bookable becomes null).
- **M6** No reserved-slug list at registration (`admin`, `www`, `api`, `mail`, `app`, `static`…).
- **M7** Bank account change + instant auto-payout, no re-auth or cooling-off → account-takeover payout risk.
- **M8** Money commands have no `withoutOverlapping()` / `onOneServer()`.
- **M9** `llms.txt` / `llms-full.txt` hard-code plan prices and limits (2/10/30 listings) that contradict the spec (5/25/unlimited) — read from `Plan`.
- **M10** `reservations/⚡index::generateBookingLink` writes undeclared `$actionSuccess` / `$actionMessage`.
- **M11** Operator "revenue" metrics sum `payments.amount` (includes the 5% guest fee); should come from wallet earnings.
- **M12** Reservation boot creates guests matching on raw phone (not normalized) → CRM duplicates; `Guest::possibleDuplicates()` loads every guest of the operator into memory.

## 4. DRY / structure (the "years of drift" list)

| # | Duplication today | Proposed single owner |
|---|---|---|
| D1 | Booking creation + terms snapshot built twice (`⚡booking-box::submitBooking`, `reservations/⚡index::generateBookingLink`) | `ReservationBookingService::createHold(Bookable, Operator, GuestData, date, pax, ?couponCode, source)` — blackout + capacity + snapshot + coupon + payment session. |
| D2 | Price/fee/discount/total computed in 4 places (booking-box, pay-link quote, `DokuPaymentService::createPaymentSession`, receipt/e-ticket views) | `BookingPricingService::quote(): PriceQuote` DTO; payment split reads the DTO, never client state. |
| D3 | Cancel/refund/confirm/complete logic in `GuestCancellationService`, `reservations/⚡show`, `DokuPaymentService::applyExternalRefund`, `MarkCompletedTripsCommand`, `ExpireStaleHoldsCommand` | `ReservationLifecycleService` (confirm, decline, cancel(actor), complete, expire) + `ReservationStatus::canTransitionTo()`. |
| D4 | "Booking confirmed" side-effects (guest mail, operator mail, vendor dispatch) copied in webhook and show page | `BookingNotificationService` (queued). |
| D5 | Payout disburse/approve/reject split between `WalletService` and admin component | `PayoutService` (see C6). |
| D6 | Plan activation (`create SubscriptionPayment` + `operator->update([...plan fields])` + `unsetRelation`) ×4 in `SubscriptionProrationService`; status strings `'pending'/'completed'/'failed'` | private `activatePlan()`; `SubscriptionPaymentStatus` enum; `PlanLimitService::enforce()` reused by lapse/downgrade/complimentary/publish. |
| D7 | Coupon lookup query ×6 (`plan`, `plan-checkout` ×2, proration service, booking-box ×3) | `PlatformCoupon::findForSubscription($code, $operator)` / `findForGuest($code, $operator)`; redemption recorded in one place. |
| D8 | Blackout queries duplicated in `Product` and `Package` (`isBlackedOutOn`, `getBlackoutDates`), called via `method_exists` | `AvailabilityService`; add both methods to `Bookable`; `CapacityService::reserve()` checks blackouts too. |
| D9 | Phone normalization ×5 (`Guest::normalizePhone`, `Guest::getCleanPhone`, `WhatsAppDispatchService`, `DokuPaymentService::sanitizePhoneNumber`, lookup) | One `PhoneNumber` helper. |
| D10 | Operator cache flush copied in admin operators show/index | `OperatorCacheService::flush()`. |
| D11 | Free-cancel cutoff in `Reservation` and `HasCancellationPolicy` | Single implementation (timezone-aware, M1). |
| D12 | Magic numbers: hold 30 min (×5, while `PlatformSetting::getBookingHoldMinutes()` exists), min payout 50k, auto-disburse 10M, dispute fee | Config / `PlatformSetting` getters. |
| D13 | Dead/legacy: `agent()`/`currentAgent()`/`getAgent*` aliases, `EnsureOperatorPortalOpen` (always open), custom payment-gateway settings (always rejected), `Operator::getPaymentGatewayConfig`, `commission_rate` fallback | Remove after the above lands. |

## 5. Proposed order of work

1. **Phase 0 — hotfixes (small, each with a Pest test):** C1–C7, H1, H8, H5 (coupon usage on DOKU path).
2. **Phase 1 — money services:** D2 → D1 → D3/D4 → D5 (fixes C4, C6, H3, H6, H7 structurally), then D6/D7 (H4, H5).
3. **Phase 2 — robustness:** M1 timezone, M2 queued mail/webhook, M3, M5 ledger constraints, H2 role/feature gates as middleware/trait.
4. **Phase 3 — cleanup:** D8–D13.

Livewire rule for everything above: any public property that influences money (discounts, totals, target plan, status) must be `#[Locked]` or recomputed server-side inside the action.

---

## 6. Phase 0 status (2026-10-02)

Fixed in the working tree (not committed), covered by `tests/Feature/Security/MoneyHardeningTest.php`:

| Item | Change |
|---|---|
| C1 | `Operator::assertRealMoneyMovementAllowed()`; enforced in `WalletService::createPayoutRequest`, payout-bank settings, `DokuPaymentService::disbursePayout`. Demo refunds are marked locally, never sent to DOKU. All DOKU calls (checkout, status query, refund, disbursement) now sign with the operator's mode. |
| C2 | Booking box: `appliedCouponCode` / `discountAmount` are `#[Locked]`; `submitBooking()` re-validates the coupon and refuses unpublished/unsellable listings. |
| C3 | Plan page: `target_plan_id`, `appliedCouponCode`, `discountAmount` locked; `confirmPlanSwitch()` re-derives the discount, only accepts active plans, normalises interval, gateway fixed to `doku`. Auto-renew toggle and cancel-downgrade now require `manageBilling`. Checkout coupon edits only on pending invoices. |
| C4 | `ReservationStatus::operatorTransitions()` / `canOperatorTransitionTo()`; reservation page re-checks every rule on execute, `pendingStatusValue` locked, `manageReservations` required. Manual confirm of unpaid bookings (cash/offline) is kept as an intended feature. |
| C5 | FAILED/EXPIRED webhook only touches a *pending* payment under a row lock and only a `payment_pending` booking. (Timestamp freshness not enforced — replays are now harmless; revisit once DOKU retry header behaviour is confirmed.) |
| C6 | `WalletService::disbursePayout()` claims Pending→Processing atomically, bank call runs after the ledger commits, stable `PO-DISB-{reference}`; approve/reject lock the row and refuse terminal states (reject runs once). Admin "send via DOKU" uses it. Also fixed `processed_by` overflow (`char(26)`) in the admin trigger label. |
| C7 | `User::isAdmin()` reads `is_admin` only. **Before deploy:** confirm the real admin rows have `is_admin = 1`. |
| H1 | `/reservations/{token}/pay` only works for `payment_pending`; re-checks blackouts; hold length from `PlatformSetting::getBookingHoldMinutes()`. |
| H5 | Subscription coupon usage counted once inside `completePendingPayment()` (covers DOKU webhook, return-sync, simulator, zero-amount). |
| H8 | Reservation "sync payment" calls `syncPaymentStatus()`. |
| D7 | `PlatformCoupon::findForSubscription()` / `findForGuest()` replace 6 copied queries. |

Open decision: implementing `MustVerifyEmail` (the `verified` middleware is still a no-op) changes the sign-up flow — needs a backfill of `email_verified_at` for existing users first.
UI follow-up for Antigravity: reservation page still shows "Cancel" on Expired bookings; the server now refuses it — drive buttons from `ReservationStatus::operatorTransitions()`.

---

## 7. Phase 1 — shared business services (2026-10-02)

Rule going forward: **a business rule lives in exactly one service or model method; Livewire pages, controllers, commands and Blade only call it.**

| Concern | Single owner | Replaced copies in |
|---|---|---|
| Price, fee, promo, total | `BookingPricingService::quote()` → `BookingQuote` (also `toSnapshot()`) | booking box, pay-link quote + creation |
| Creating a hold | `ReservationBookingService::createHold()` (publish check, blackout, quote, capacity lock, coupon count, payment session, hold mail) | booking box, operator pay link |
| Status changes | `ReservationLifecycleService` — `applyPaidPayment`, `applyFailedPayment`, `applyGatewayRefund`, `transitionByOperator` (+ `operatorBlockReason`), `cancelByGuest`, `cancel` (per-booking lock, one refund), `complete`, `expireStaleHolds`, `completeFinishedTrips` | DOKU webhook, reservation page, `GuestCancellationService` (deleted), expire/complete commands |
| Booking messages | `BookingNotificationService` — `holdCreated`, `bookingConfirmed`, `bookingCancelled` | webhook, reservation page, booking box |
| Bookable behaviour | `Models\Traits\InteractsWithBookable` (getters, terms snapshot, blackouts); `Bookable` now declares `isBlackedOutOn` / `getBlackoutDates` | ~350 duplicated lines in `Product` / `Package`, `method_exists` checks |
| Free-cancel cutoff | `CancellationPolicy` | `Reservation`, `HasCancellationPolicy` |
| Phone → WhatsApp | `PhoneNumber::normalize()/digits()/whatsAppLink()` | Guest (×2), WhatsApp service, DOKU, 9 Blade spots (several built `wa.me/08…` links that never worked) |
| Who gets emailed | `Operator::bookingNotificationRecipient()`, `billingRecipient()`, `accountRecipient()` | webhook, renewal command, inactivity command, admin plans (×2) |
| Guest totals | `Reservation::getQuotedTotal()`, `getChargedAmount()`, `hasValidTicket()` | receipt, e-ticket, pay route, coupon report (×4), guest profile, admin operator page |
| Operator revenue | `Operator::paidGuestPaymentsTotal()` | dashboard, reservations, admin operator page, coupon rule (also fixes the `'completed'` status bug) |
| Operator status | `OperatorAccountService::changeStatus()` | admin list, admin detail, inactivity command (dead cache keys removed) |
| Plan activation | private `activatePlan()` + `recordInvoice()` in `SubscriptionProrationService`; `SubscriptionPayment::STATUS_*` | 4 copies; prorated upgrades now keep the paid-for period end |
| Plan limits | `PlanLimitService::enforce()` / `canPublish()` | lapse only → now also downgrades, scheduled downgrades, admin grants, and re-publishing from edit |
| Renewal reminder | `SubscriptionReminderService` | command + admin plans |
| Card disputes | `WalletService::openCardDispute()` | webhook + admin (amounts differed) |
| Plan list / plan copy | `Plan::catalog()`, `PlatformSeoService::planSummaryLines()` | welcome, plan page, admin, hard-coded llms.txt prices |

Also in this pass: catalog/guest-CRM writes require `manageCatalog` / `manageReservations`; product vendor must belong to the operator; `cancelBookingEarning()` reverses once; replayed DOKU success cannot re-open a refunded payment; late payments on expired holds are honoured if seats are free, otherwise auto-refunded.

Tests: full suite **611 passing** (PHP 8.5), new `tests/Feature/Services/SharedBusinessServicesTest.php` and `tests/Feature/Security/MoneyHardeningTest.php`. Pint clean; PHPStan shows no new errors in touched files.

Still open (next pass): SEO/robots/sitemap builders inside `StorefrontController`; capacity heatmap's own capacity math; dashboard/metrics count queries; business timezone (M1); queued mail (M2); legacy `agent` aliases.

---

## 8. Platform admin audit (2026-10-02)

Scope: `/admin` (login, dashboard, operators, plans, subscriptions, announcements, coupons, payouts, platform settings, admins, profile), admin middleware, impersonation.

### Critical
| # | Where | Problem | Fix |
|---|---|---|---|
| AD1 | `admin/⚡login::login()` | Admin sign-in calls `Auth::attempt()` directly, so **2FA and passkeys are never challenged**. The Admins page counts "2FA enabled" admins, giving false assurance. | Route admin login through Fortify's 2FA challenge (or require a confirmed 2FA/passkey before `/admin` loads). |
| AD2 | `bootstrap/app.php`, Livewire | `admin` (EnsureUserIsAdmin) and `platform` middleware are **not Livewire persistent middleware**, so they only run on the first page load. Actions on an open admin page keep working after `is_admin` is revoked (incl. `createAdministrator`). | `Livewire::addPersistentMiddleware([EnsureUserIsAdmin::class, EnsureOnPlatformDomain::class])`; add a test that a revoked admin's action is refused. |

### High
| # | Where | Problem |
|---|---|---|
| AD3 | all admin pages | **No admin audit log.** Plan grants/extensions, payout approve/reject/send, dispute holds, operator status, impersonation, settings and admin creation leave no who/when/what record (Slack covers only some). |
| AD4 | operators `manageOperator()`, `User::currentOperator()` | Impersonation gives full Owner rights (bank change + payout request), has no exit, expiry or log. An admin on the desk **without** impersonating falls back to `Operator::query()->first()` — a random operator. |
| AD5 | `admin/⚡profile`, `admin/⚡admins` | Change email, disable 2FA, delete passkeys and create new admins need no password re-confirmation. |
| AD6 | `platform_coupons.code` unique index vs operator coupon form | Code is unique **globally**; operator form only checks its own codes → saving a code another operator or the platform uses throws a 500 and reveals it exists. Needs a composite unique (`scope`, `operator_id`, `code`). |
| AD7 | `admin/⚡plans::extendSubscription()` | Accepts any day count (negative/huge), writes `plan_expires_at` directly, no invoice row; bypasses `SubscriptionProrationService`. |

### Medium
- AD8 MRR counts lapsed, grace-period and complimentary operators at full price.
- AD9 Coupon usage report: revenue filters `status = 'paid'` (never true for subscriptions) → always 0; pending/failed invoices counted as uses.
- AD10 Dashboard shows only subscription revenue. Guest service fee (main revenue), GMV, gateway fees, open disputes and escrow liability are missing.
- AD11 Settings: currency is editable though checkout is IDR-only; legacy commission % (and new plans default to 10%) still feeds the old payment-split fallback; Slack webhook URL isn't validated as a Slack URL; guest-fee cap isn't editable.
- AD12 Finance tools missing: no dispute "lost" outcome, no admin refund/cancel, no reasoned manual adjustment — work ends up in the database.
- AD13 Admin "remember me" defaults on; admin login tells an operator their password was right ("Access denied") vs wrong.
- AD14 Platform-root host list includes `localhost`, `127.0.0.1` and dev domains in production.

### DRY
Pending-payout totals ×3 (dashboard, payouts, admin sidebar); subscription revenue ×3 (dashboard card, chart, subscriptions page) with raw `'completed'` strings; auto-renew toggled in two places; plan dates changed outside the subscription service (extend); coupon page loads every operator + users on each render. Proposed: `AdminMetricsService` and moving extend/auto-renew into `SubscriptionProrationService`.

## 9. Admin fixes (2026-10-02) — all AD items closed

| Item | Fix |
|---|---|
| AD1 | Admin login validates credentials without signing in; admins with 2FA are sent through Fortify's challenge. Passkey/2FA sign-ins for admins land on `/admin`. |
| AD2 | `EnsureUserIsAdmin` and `EnsureOnPlatformDomain` registered as Livewire persistent middleware. |
| AD3 | `admin_audit_logs` table (immutable), `AdminAuditLogger` service, `RecordsAdminActions` trait, `/admin/audit-log` page. Logged: sign-in, operator status, start/stop managing, plan create/update/reset/grant/extend/auto-renew/reminder, payouts approve/reject/send, dispute hold/release/lost, admin cancel, wallet adjustment, coupons, announcements, settings (changed keys), maintenance, failed-job retry/flush, admin create/revoke, email/password/2FA/passkey changes, CSV exports. |
| AD4 | Managing a shop goes through `OperatorAccountService::startManaging/stopManaging` (logged), expires after 120 min, `POST /admin/stop-managing`. Admins never fall back to an arbitrary operator. Payouts and bank changes are blocked while managing (same gate as demo). Admins no longer count as operator activity. |
| AD5 | `/admin/admins` and `/admin/profile` require `password.confirm`. |
| AD6 | Migration: coupon codes unique per (`scope`, `operator_id`, `code`); admin subscription codes unique within subscription scope. |
| AD7 | `SubscriptionProrationService::extendPeriod()` (1–366 days, Rp 0 `admin_extension` invoice) and `setAutoRenew()` shared by admin and operator pages. |
| AD8–10 | `AdminMetricsService`: MRR (paying, live periods only), subscription revenue + monthly series, payout totals, guest-fee revenue, GMV, escrow liability, cleared balances, open dispute holds, coupon usage (paid invoices only). Used by dashboard, payouts, subscriptions, coupons, admin sidebar. |
| AD11 | Currency locked to IDR; Slack webhook must be `https://hooks.slack.com/…`; guest-fee cap editable (`guest_service_fee_cap`); legacy payment-split fallbacks no longer take an operator commission (operators keep 100%). |
| AD12 | `ReservationLifecycleService::resolveCardDispute()` (won/lost; lost = final chargeback deduction + booking cancelled), `WalletService::recordManualAdjustment()`; admin operator page: `cancelBooking`, `markDisputeLost`, `adjustWallet`. |
| AD13 | Same error for wrong password and non-admin; remember-me off by default. |
| AD14 | `localhost`/dev hosts only count as platform outside production. |

New Livewire state for Antigravity to surface: admin operator page `adjustmentAmount`, `adjustmentReason`, actions `adjustWallet`, `cancelBooking(id)`, `markDisputeLost(id)`; platform settings `guest_service_fee_cap`; dashboard computed `guestFeeRevenue`, `grossBookingValue`, `escrowLiability`, `clearedOperatorBalances`, `openDisputeHolds`; a "Stop managing" button posting to `admin.operators.stop-managing`. The audit-log page uses existing components and a plain table.

Deploy notes: run `php artisan migrate` (2 migrations). Suite: 631 passing.

## 10. Fresh-prod baseline and MVP alignment (2026-10-02)

### Migrations: one create per table
- 17 migration files, each table created exactly once in its final shape, ordered by foreign keys. No `Schema::table` and no drops in `up()` (enforced by `SchemaBaselineTest`).
- Folded in: `vendors` now created before `products` (with `vendor_id`), `operators.last_active_at` (indexed) and `inactivity_reminder_sent_at`, platform coupon codes unique per owner (`scope`, `operator_id`, `code`), plan `commission_rate` default 0.
- Removed: `reviews` create/drop pair, dead `product_availability` table with its model, factory and `Product::availabilityRecords()`, and the five alter/drop migrations.
- Verified: `migrate:fresh --seed` on SQLite and MariaDB 10.11, `migrate:reset` then `migrate` on MariaDB, and the full suite on both databases.
- Demo seeder no longer fails when the server cannot reach Unsplash (it falls back to placeholder images).
- **Prod note:** these migrations are only for an empty database. Do not run them against an existing database that already has the old migrations recorded.

### Promo redemption rules now enforced
- "First purchase only" and "once per period" were stored but never checked, and redemptions were never logged.
- `PlatformCoupon::validateFor()` now applies the rule for subscription codes.
- A paid invoice calls `recordRedemptionBy()`, which bumps `used_count` and writes `operator_coupon_redemptions`.

### Security leftovers
- JSON-LD on storefront and platform pages is encoded with `JSON_HEX_TAG`, so operator text cannot close the script tag.
- SVG uploads are refused (brand logo validation and `MediaStore`), because they can carry script on the shop's own domain. PDF proofs still work.
- Reserved shop addresses (`Operator::RESERVED_SLUGS`, for example admin, www, api, mail, demo) are rejected at sign-up and skipped by the auto-slug.
- Find booking now needs the exact booking email or the same phone number after normalising (at least 8 digits). Guest names and phone fragments no longer open the e-ticket. The logic is `Reservation::matchesGuestContact()`.
- The inactivity job no longer suspends operators who are on a running paid plan or whose guests hold upcoming bookings. Those operators still get the reminder (`Operator::hasLiveCommitments()`).

### Email verification
- `User` implements `MustVerifyEmail`.
- The verification link opens on the operator's slug host, where their session lives (`Operator::slugDeskRoot()`, shared with the login and register handoffs).
- Setting a password from an emailed reset link (team invites) marks the address verified.

### Queues and scheduler
- Booking, vendor and renewal mails go through the queue explicitly. Database and redis queues use `after_commit`, so a mail is never sent for a booking that rolled back.
- Every scheduled command uses `withoutOverlapping()->onOneServer()`, and daily jobs run at fixed WITA times. This needs a shared cache store (database or redis).

### Bali time
- `app.timezone` defaults to `Asia/Makassar` (`APP_TIMEZONE`).
- WITA now applies to "today", trip completion, free-cancel cutoffs, escrow release and the scheduler.
- iCal `DTSTAMP` is converted to UTC explicitly. DOKU request timestamps already used `gmdate`.

### Checks
- 649 tests pass on SQLite and on MariaDB.
- Pint is clean.
- PHPStan errors went down from about 50 to 43; the remaining ones were already there before this work.

### Moving from Caddy to Laravel Cloud
Decided: Laravel Cloud plus Cloudflare R2. The work is done in section 11.

## 11. Third-party integrations and Laravel Cloud (2026-10-02)

### One client per third-party service (`app/Services/Integrations`)
| Service | Client | Business service that uses it |
|---|---|---|
| DOKU Jokul | `DokuClient`: credentials, mode, signing, webhook signature, checkout, status, refund, BI-FAST transfer, bank code, phone format | `DokuPaymentService` (money rules only) |
| Slack | `SlackClient`: posts only to `https://hooks.slack.com/` | `OperatorActivitySlackNotifier` (message content) |
| Google Places | `GooglePlacesClient`: place details, text search, Maps-link expansion | `GooglePlacesService` (operator listing snapshot) |
| Laravel Cloud | `LaravelCloudClient`: create, get, verify and delete domains | `CustomDomainService` through `LaravelCloudDomainProvider` |
| Cloudflare R2 | Laravel `r2` disk (S3 driver) | `MediaStore` |

- Security fix found on the way: a pasted "Google Maps link" was fetched with redirects to any address, so an operator could make the server call internal addresses (SSRF). The client now only follows https Google Maps hosts, including across redirects.
- The Slack client also refuses any non-Slack URL.

### Custom domains (Agency)
- `CustomDomainService` owns connect, check and disconnect. It refuses platform hosts, addresses another operator uses, IP addresses and malformed names before calling any provider.
- Providers implement `App\Contracts\CustomDomainProvider` and are selected by `CUSTOM_DOMAIN_PROVIDER`:
  - **`laravel_cloud`:** adds the address to the environment (real-time verification, Cloudflare strategy "none"). It stores Cloud's DNS records: an A record to the Cloud origin IP for an apex, a CNAME to Cloud's hostname for a subdomain, plus any certificate records. Status is Active only when hostname, SSL and origin are all verified.
  - **`caddy`:** the previous self-hosted flow (DNS check, then the HTTPS probe for the padlock). It is kept for local work and as a fallback.
- `operator_domains` gained `provider`, `provider_ref`, `dns_records` and `last_checked_at`, in its create migration.
- Verifying custom domains already route to the shop. Storefront links switch to the custom domain only once it is Active.
- `domains:check` (every 5 minutes) replaces `domains:probe-ssl`.
- Changing or clearing an address removes it from Cloud first. If Cloud is unreachable the row is kept, so no address is left behind counting toward the allowance.
- Brand settings now call the service, and `customDomainRecords()` gives the UI the exact records to show. **UI to-do (Antigravity):** render those records in place of the fixed Lightsail IP/CNAME instructions when the provider is Laravel Cloud.

### Media on Cloudflare R2
- The `r2` disk sends no ACL, because R2 rejects `public-read`. Public URLs come from `R2_URL`, the bucket's public domain, and errors throw.
- `MediaStore` sets visibility only on local disks, and fails loudly if a write fails.
- **Needs** `composer require league/flysystem-aws-s3-v3 "^3.0"`. The package registry was not reachable from the build environment, so this has to be run locally and committed with `composer.lock`.

### Production environment (Laravel Cloud)
- `MEDIA_DISK=r2` and `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com`, `R2_URL=https://storage.travelengine.id`
- `CUSTOM_DOMAIN_PROVIDER=laravel_cloud`, `LARAVEL_CLOUD_API_TOKEN`, `LARAVEL_CLOUD_ENVIRONMENT_ID`
- Wildcard `*.travelengine.id` plus `travelengine.id` on the environment. Keep the `_acme-challenge` CNAME permanently; on Cloudflare it must be DNS only.
- `APP_TIMEZONE=Asia/Makassar`, a queue worker, the scheduler enabled, and a database or redis cache.
- Leftovers that are unused on Cloud: `CaddyAskController`, `PLATFORM_PUBLIC_IPV4/6` and `CADDY_ASK_TOKEN`. They only matter with `CUSTOM_DOMAIN_PROVIDER=caddy`.

### Checks
- 665 tests pass.
- Pint is clean.
- No new PHPStan errors in the touched files.

## 12. Laravel Cloud readiness and docs tidy-up (2026-10-02)

### Laravel Cloud readiness
- **Packages:** `league/flysystem-aws-s3-v3` is installed, so the R2 disk is usable.
- **`php artisan cloud:environments`:** checks `LARAVEL_CLOUD_API_TOKEN` and lists environment ids for `LARAVEL_CLOUD_ENVIRONMENT_ID`.
  - Neither this environment nor the Mac VM could reach the Cloud API, so run it locally or from Cloud Commands.
  - Domain calls now say clearly when the environment id is missing.
- **Trusted proxies:** these were hard-coded to `127.0.0.1`. Behind Cloud's edge, that would have made every request look like plain http from the edge IP, breaking secure URLs, guest IPs and rate limits.
  - They are now config-driven: `TRUSTED_PROXIES`, with `*` on Cloud.
  - They are applied in `AppServiceProvider`, so they still work with cached config.
- **Admin seeder:** it read `env()` directly. With cached config on Cloud, `ADMIN_PASSWORD` would be empty and production seeding would refuse to run. It now reads `config/platform.php`.
- **Open:** `.github/workflows/deploy.yml` still deploys to Lightsail on every push to `main`. Disable it once Cloud is live.

### Docs
- `docs/` is now grouped into `product/`, `commercial/`, `features/`, `engineering/` and `audits/`, with an index and maintenance rules in `docs/README.md`.
- **New:** `engineering/integrations.md`, `engineering/production-launch.md` and `features/custom-domains.md`.
- **Brought in line with the code:**
  - Hosting (Laravel Cloud, R2, WITA) and the Laravel 13 version.
  - Find-booking rules, email verification, reserved slugs and coupon redemption rules.
  - Scheduler command names and times.
  - The fee base: the guest fee is on the subtotal before an operator promo.
- **Money rules:** the doc now opens with an implementation-status table that separates what is automated from policy only (operator-fault fee debit, demand notices, write-off, settlement-file reconciliation).
- **Pricing:** the stale four-tier, bring-your-own-gateway matrix was replaced. The 2026-08 commercial audit was moved to `audits/archive/` with a superseded banner.

## 13. Secrets split and cleanup (2026-10-02)

### `.env` and secrets
- **`.env.example`:** now two blocks.
  - **GENERAL:** per-environment settings.
  - **SECRETS:** always empty. Set them in Laravel Cloud (environment variables or Secrets Manager), or locally in your own git-ignored `.env`.
  - Everything else has a default in `config/*.php`. For example, DOKU endpoints are fixed per mode, and the R2 region and WebP quality are fixed.
- **Not stored in committed config:** secret values do not live in `config/platform.php` or any other committed file, because the repository is on GitHub. Config files only *read* secrets, with no default value.
- **Removed a real leak:** `PlatformSetting::current()` copied the DOKU client ids, secret keys and SNAP private keys into the `platform_settings` table on first boot. Those copies were never read.
  - The copy is gone, and so are the twelve unused DOKU getters on `PlatformSetting`.
  - DOKU mode and credentials are now read only by `DokuClient` (`config/doku.php`: `mode`, client id and secret key per mode).
- **Guard:** `SecretsHygieneTest` checks three things:
  - Secrets stay empty in `.env.example`.
  - No config file gives a secret a default.
  - Secrets never reach `platform_settings`.
- **Local `.env` (Mac):**
  - Regrouped into GENERAL and SECRETS. The previous file is saved as `.env.backup`.
  - Removed 29 keys that were obsolete (Caddy, Lightsail IPs, DOKU URLs) or matched config defaults.
  - Kept `QUEUE_CONNECTION=sync` for local work.

### Removed as irrelevant
- **GitHub Actions deploy:** `.github/workflows/deploy.yml` (Lightsail) is gone. Laravel Cloud deploys from GitHub, and `tests.yml` stays as CI.
- **Caddy / self-hosted TLS:**
  - Removed: `CaddyAskController` and the `/internal/caddy/ask` route, `CaddyDomainProvider`, and the DNS/IP/HTTPS-probe helpers in `DomainResolverService`.
  - Removed: `PLATFORM_PUBLIC_IPV4/6` and `CADDY_ASK_TOKEN`, plus their tests.
  - Replaced by `LocalDomainProvider` for development and tests. It refuses to run in production, where the provider defaults to `laravel_cloud`.
  - Brand settings now render the provider's DNS records instead of fixed server addresses.
- **Bring-your-own gateway leftovers:**
  - Removed the payment-gateway fields on the payout bank page (`payment_mode`, `gateway_*`) and `Operator::getPaymentGatewayConfig()`, `hasCustomPaymentGateway()` and `getPaymentGatewayProvider()`.
  - Saving the payout bank no longer writes `settings.payment_gateway`.
- **Legacy "agent" aliases:**
  - Removed the deprecated `agent()` / `agents()` relations on nine models.
  - Removed `User::currentAgent()`, `getAgent()` / `getAgentId()` on `Bookable`, `DomainResolverService::resolveAgent()`, `StorefrontController::resolveCurrentAgent()` and `OperatorOnboardingService::registerAgent()`.
  - All callers now use `operator`.
- **Docs:** updated for Laravel Cloud deploys from GitHub, the secrets split, the local provider and the DOKU config. The old Lightsail section was removed.

### Still open (needs a product decision)
- **Commission fields:** operator commission is always 0%, but Admin → Settings and Admin → Plans still show editable commission fields that change nothing. The plan renewal email also shows the plan's commission. Removing them touches the admin UI.
- **`.env.live` (Mac, git-ignored):** this is the old Lightsail production environment, with live DOKU, R2, database and mail secrets, plus unused DOKU SNAP keys.
  - Move what is still needed into Laravel Cloud, then delete the file.
  - Do the same for `private.key` / `public.key` (DOKU SNAP RSA keys, unused by the code).
- **Local secrets:** the local `.env` holds live DOKU keys, which a laptop does not need. Its `APP_KEY` also equals the one committed in `.env.testing`, so run `php artisan key:generate` locally.
