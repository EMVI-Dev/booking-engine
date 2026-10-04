# Operator Portal, Coupons & Financial Operations Spec

_Version: 2026-09-27 · Last reviewed: 2026-10-03 · TravelEngine Core Platform_

This document specifies the architecture, security isolation, and operational workflows for promotional coupons, dedicated analytics reporting, billing invoices, guest CRM profiles, and subdomain desk authentication handoffs.

---

## 1. Promotional Coupons & Dedicated Performance Reports

### 1.1 Scope Isolation (`guest` vs `subscription`)

`App\Models\PlatformCoupon` serves two separate domain functions differentiated by the `scope` column:

| Scope | Managed By | Purpose | Intended Context |
| :--- | :--- | :--- | :--- |
| `subscription` | Platform Admins | Discounts on operator monthly/annual subscription fees | Operator checkout at `/settings/plan-checkout` |
| `guest` | Tour Operators | Direct discount codes for travelers booking activities | Storefront booking box (`livewire:storefront.booking-box`) |

#### Standing Rules:
- **Operator Catalog Queries**: All operator portal views and counts (including the sidebar active badge and `coupons.index`) **MUST** scope queries using `PlatformCoupon::forGuest()->where('operator_id', $operator->id)`.
- **Targeted Platform Billing Coupons**: An admin can target a subscription coupon to an operator (`operator_id = '...'`). Without `forGuest()` scoping, this billing discount would leak into the operator's public storefront discount list.
- **Storefront Validation**: the booking box looks codes up only with `PlatformCoupon::findForGuest()` (that operator's guest codes), and the discount is recalculated on the server by `BookingPricingService`. It is never taken from the browser.
- **Uniqueness**: codes are unique per owner (`scope`, `operator_id`, `code`), so different operators may reuse the same code.
- **Subscription promo rules**: `redemption_scope` (`unlimited`, `first_purchase_only`, `once_per_period`) is enforced when the code is applied. Each paid invoice records a row in `operator_coupon_redemptions` (`PlatformCoupon::recordRedemptionBy()`).

### 1.2 Dedicated Coupon Report Page (`/coupons/{coupon}/report`)

Coupon performance analytics are rendered on a dedicated full-page Livewire component (`pages::coupons.report`) registered at `route('coupons.report', $coupon)`:

#### Core Components:
1. **Promo Configuration Card**:
   - Code badge, discount rate (`%` or fixed `Rp`), active status, quota progress bar, minimum spend, cap, and validity window.
2. **5 Key Financial Metrics**:
   - **Total Redemptions**: Total uses against quota and count of unique guests.
   - **Discounts Given**: Total monetary savings provided to guests, plus average discount per booking.
   - **Gross Booking Volume**: Total value before coupon deduction.
   - **Net Revenue Collected**: Real proceeds collected from checkouts with this code.
   - **Average Order Value (AOV)**: Average booking subtotal with this code.
3. **Filtering & Search Toolbar**:
   - Debounced live search across booking reference (`#RSV-...`), guest name, email, and phone.
   - Status tabs: *All Statuses*, *Confirmed*, *Completed*, *Pending Payment*, *Cancelled*.
   - Sorting dropdown: *Newest First*, *Oldest First*, *Highest Discount*, *Highest Booking Value*.
4. **Itemized Redemption Transactions Table**:
   - Direct link to each reservation (`route('reservations.show', $res->code)`).
   - Guest profile info with quick WhatsApp chat shortcut.
   - Experience title and pax count.
   - Itemized financial breakdown: Subtotal, Discount (`- Rp`), and Net Paid.
   - Booking status pills and pagination.
5. **CSV Export**:
   - Streamed CSV download (`coupon-report-{code}-{date}.csv`) containing all redemptions with financial breakdown and booking details.
6. **Eager Loading & Lazy Loading Prevention**:
   - When aggregating financial metrics, reservations must be retrieved with `latestPayment` eager-loaded (`baseRedemptionsQuery()->with('latestPayment')->get()`) to comply with `Model::preventLazyLoading(true)`.

---

## 2. Operator Authentication & Subdomain Desk Handoff

### 2.1 The Multi-Tenant Apex Challenge
Operators frequently sign in from the marketing landing page on the platform apex domain (`travelengine.id/login`). However, each operator manages their catalog on their own branded subdomain desk (`{slug}.travelengine.id/dashboard`).

### 2.2 Secure Signed Handoff Flow
1. Upon successful authentication (password, passkey, or 2FA challenge), `App\Http\Responses\LoginResponse`, `PasskeyLoginResponse`, and `TwoFactorLoginResponse` detect if the user has an active operator.
2. If the user is currently on the platform apex, `AuthHandoffService` builds a one-time link to their slug desk:
   `https://{slug}.travelengine.id/auth/login-handoff?user=...&host=...&nonce=...&expires=...&signature=...`
   It is signed, expires in 5 minutes, works only on the host it was made for, and can be used once (the nonce is consumed from the cache).
3. The destination controller (`LoginHandoffController`) consumes the link, checks the user belongs to that shop, signs them in, keeps "remember me", and redirects to the intended desk URL (default `/dashboard`). A used, expired or foreign link gets a 403 asking them to log in.
4. Operators already logging in from their own subdomain desk remain on that host without unnecessary redirects.
5. Registration uses the same signed handoff. New operators must verify their email; the verification link is built for the slug host (`Operator::slugDeskRoot()`), where their session lives.

---

## 3. Billing, Invoicing & Gateway Audit

### 3.1 Tab Ordering for Daily Utility
The billing management portal (`/settings/billing`) orders tabs by frequency of operational use:
1. **Payment History & Invoices** (`invoices`): Recent renewal receipts, transaction references, download/print buttons.
2. **Payouts & Disbursements** (`payouts`): Platform escrow disbursement ledger and bank transfer history.
3. **Subscription Plan & Tier** (`plan`): Upgrade, downgrade, billing cycle toggles, and cancellation controls.

### 3.2 Gateway Resolution & Security
- Gateway names and icons are resolved exclusively through `SubscriptionPayment::getGatewayLabel()` and `getGatewayIcon()`.
- Real DOKU checkout payments (`gateway = 'doku'`) and administrator complimentary grants (`gateway = 'admin_complimentary'`) are cleanly identified and never display sandbox fallback labels.
- `DokuPaymentService` enforces exact amount matching, rejecting any underpayments or zero-amount payloads.

### 3.3 Printable Invoices (`#invoice-print-portal`)
- Invoices are printed via browser print dialogue (`window.print()`).
- The printable modal teleports directly to `<body>` wrapped in `<div id="invoice-print-portal">`.
- **Do not use global `body * { visibility: hidden }`** print rules, as modern browser rendering engines (Chromium/WebKit) blank the document root.
- The portal is isolated with dedicated `@media print` rules:
  ```css
  @media print {
      body { background: #ffffff !important; color: #000000 !important; }
      #invoice-print-portal { display: block !important; position: absolute; top: 0; left: 0; width: 100%; }
  }
  ```

---

## 4. Guest CRM Profile Structure (`/guests/{guest}`)

The Guest CRM profile view (`pages::guests.show`) is structured as a dedicated customer record card:

1. **Left Profile Sidebar**:
   - Initials avatar with repeat customer badge ring.
   - Name and customer join date.
   - Quick action bar: WhatsApp one-click chat, mailto link, tel link.
   - Internal staff notes editor (auto-saving or modal-prompted).
   - Dynamic tag pills (e.g. VIP, Family, Solo, Referral).
2. **Right Financial & Trip History Pane**:
   - KPI metrics: Lifetime Spent, Total Bookings, Average Booking Value, Last Active Date.
   - Itemized reservation history with status filtering (Upcoming vs Past).
   - Duplicate profile detection card with 1-click merge suggestions.
