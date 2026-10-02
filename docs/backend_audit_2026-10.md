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
