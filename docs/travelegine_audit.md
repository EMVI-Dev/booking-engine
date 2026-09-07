# Booking Engine Platform — V1 Commercial Audit & Risk Specification (Rev. 16)

> **Canonical Financial Specification**: See [emvi_v1_money_rules_and_settlement_spec.md](emvi_v1_money_rules_and_settlement_spec.md) for the single source of truth governing all monetary calculations, ledger entries, settlement states, and risk allocation policies.

## Vision & Architecture

A generic booking and inventory engine built simple and straightforward for tour providers and travel agencies. Each operator gets their own branded booking storefront (`slug.travelengine.online` by default, upgradeable to custom domain `yourbrand.com`). There is no cross-agent search/discovery — the platform marketing site is for SaaS onboarding and operator logins only; each agent's page is its own standalone destination.

---

## 1. Commercial Model & Pricing Matrix Alignment

| Capability / Metric | **Starter Essential** | **Pro Operator** | **Agency Ultimate** | **AI Ultimate Agency** |
| :--- | :--- | :--- | :--- | :--- |
| **Monthly Subscription** | **Free / Rp 0** | **Rp 299.000 / mo** *(Rp 2.990.000 / yr)* | **Rp 699.000 / mo** *(Rp 6.990.000 / yr)* | **Rp 999.000 / mo** *(Rp 9.990.000 / yr)* |
| **Operator Payout Net** | **100% Net to Operator** | **100% Net to Operator** | **100% Net to Operator** | **100% Net to Operator** |
| **Guest Service Fee** | **5.0%** (Paid by Guest) | **5.0%** (Paid by Guest) | **0.0%** (Direct BYO Gateway) | **0.0%** (Direct BYO Gateway) |
| **Package Listings Limit** | Up to **5** Packages | Up to **25** Packages | **Unlimited Listings** | **Unlimited Listings** |
| **Team Staff Seats** | **Unlimited Staff** | **Unlimited Staff** | **Unlimited Staff** | **Unlimited Staff** |
| **Storefront Subdomain** | ✅ `slug.travelengine.online` | ✅ `slug.travelengine.online` | ✅ `slug.travelengine.online` | ✅ `slug.travelengine.online` |
| **Custom Domain (`yourbrand.com`)** | 🔒 *Gated* | 🔒 *Gated* | ✅ **Included with Auto-SSL** | ✅ **Included with Auto-SSL** |
| **Google Calendar & Live iCal Feed** | 🔒 *Gated* | ✅ **Included** | ✅ **Included** | ✅ **Included** |
| **Guest CRM Directory & LTV** | 🔒 *Gated* | ✅ **Included** | ✅ **Included** | ✅ **Included** |
| **1-Click WhatsApp Dispatch Center** | 🔒 *Gated* | ✅ **Included** | ✅ **Included** | ✅ **Included** |
| **Payment Gateway Credentials** | Shared Platform Gateway | Shared Platform Gateway | **BYO Custom Merchant Keys** | **BYO Custom Merchant Keys** |
| **AI Search Discovery (`/llms.txt`)** | 🔒 *Gated* | 🔒 *Gated* | 🔒 *Gated* | ✅ **Exclusive Flagship Included** |

---

## 2. Six Core Financial Pillars

1. **Commission Specification**: `0.00%` operator take rate (100% Net listed price goes to operator wallet).
2. **Payment Economics**: Guest pays `100% listed price + 5% Guest Service Fee` on Starter & Pro at checkout. Platform margin = 5% Guest Fee minus DOKU gateway cost.
3. **Free-Tier Economics**: Starter Essential is Free (Rp 0/mo) for up to 5 package listings with 100% Net payout.
4. **AI Tier Economics**: AI Ultimate Agency (`Rp 999.000 / mo`) provides dynamic `/llms.txt` search feeds and unblocked AI crawlers in `/robots.txt`.
5. **Wallet / Escrow & Automated Reversals**: Escrow holds mature to `Cleared` balance on trip departure date. Automated reversal engine handles `PendingEscrow` cancellations and `Cleared` debit reversals.
6. **Cancellation Engine & Snapshot Cutoffs**: Snapshot-frozen policy terms (`terms_snapshot['free_cancellation_hours']`), eligibility checking (`isEligibleForFreeCancellation()`), and exact cutoff calculations.

---

## 3. Formalized Financial Edge Cases & Risk Policies

1. **Refund After Payout (Clawback & Negative Wallet Balance)**:
   - Debit logged via `RefundDeduction` / `ManualAdjustment`. Insufficient cleared funds result in `available_balance < 0`.
   - 100% of future earnings automatically offset negative balance before payouts unlock (`available_balance >= Rp 50.000`).

2. **Uncollectible Negative Wallet Balance Policy**:
   - 30-day auto-demand notices sent to operator; default payment method charged.
   - Guest made whole immediately from platform reserves. Legal debt recourse retained against operator entity under Merchant Terms.

3. **Chargebacks & Fraud Disputes**:
   - DOKU chargeback alerts trigger a `DisputeHold` for dispute amount + non-refundable bank dispute fee (Rp 150.000).
   - Won dispute releases hold; lost dispute debits fee + refund from operator wallet.

4. **Payment Failure After Reservation Creation**:
   - Unpaid 30-minute hold reservations expire automatically (`platform:expire-holds`). Zero ledger entries written for unpaid reservations.

5. **Partial Refunds**:
   - Partial refunds log a `RefundDeduction` debit for exact amount via `WalletService::processPartialRefund()`.

6. **Operator Cancellation (Weather / Mechanical / Operator Fault)**:
   - Guest receives 100% full refund (including 5% Guest Service Fee).
   - Operator wallet is debited for full refund + 5% Guest Service Fee so platform is not penalized.

7. **Gateway Processing Fee Treatment on Refunds**:
   - Guest-initiated (within policy): Platform absorbs non-refundable DOKU processing fee (0.7% QRIS) from margin.
   - Operator-initiated: Operator wallet debited for gateway processing fee.

8. **Payout Timing & Settlement Schedule**:
   - Escrow matures T+0 on trip departure date (`requested_date`). Daily cron (`platform:release-escrows` at 00:05 AM WIB) clears escrows. Payout requests before 12:00 PM WIB process T+1.

9. **Ledger Reconciliation (DOKU vs. EMVI)**:
   - Daily reconciliation (`platform:reconcile-doku`) cross-checks DOKU bank settlement files against `Payment` `gateway_ref` invoice numbers and `WalletTransaction` gross/net entries.

10. **Financial Loss Allocation Matrix**:

| Risk Event | Guest Status | Operator Impact | EMVI Platform Impact |
| :--- | :--- | :--- | :--- |
| **Guest Cancellation (In Policy)** | 100% Refund | 0 Escrow Earned | Absorbs gateway fee from margin |
| **Guest Cancellation (Late / No Show)** | 0% Refund | 100% Net Payout Retained | Retains 5% Guest Service Fee |
| **Operator Cancellation** | 100% Refund (Incl. Fee) | Wallet Debited for Fee Loss | Made whole via Operator Wallet Debit |
| **Chargeback Fraud (Lost)** | Refunded by Issuing Bank | Wallet Debited + Dispute Fee | Absorbs loss if operator insolvent |
| **Uncollectible Negative Balance** | Made Whole Immediately | Debt Owed (30-Day Collections) | Loss provisioned, legal debt recovery |

---

## 4. Technical Stack & Quality Assurance

- **Framework**: Laravel 12 on PHP 8.5 with Pest 5 testing (**174 automated test suites passing**).
- **UI Stack**: Livewire 4 Single-File Components (SFC), Tailwind CSS v4, Alpine.js, FontAwesome 6 icons.
- **Database Safety Guard**: Tests run strictly in `:memory:` SQLite; hard runtime assertion prevents accidental production database truncations.
- **Code Formatter**: Formatted with Laravel Pint (`vendor/bin/pint --format agent`).