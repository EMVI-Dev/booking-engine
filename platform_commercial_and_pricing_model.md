# Platform Commercial & Pricing Model Specification

This document defines the official commercial architecture, guest fee structure, payment processing pass-through rules, and operator disclosures for the Booking Engine Platform.

---

## 1. Executive Summary & Industry Standard

Following the industry model used by **FareHarbor (Booking.com)**, **Loket.com**, and **Megatix**:

> **Core Principle**: **Operators keep 100% of their listed tour package prices.** 
> The platform generates automated transaction revenue by adding a standard, transparent **Guest Service Fee** (e.g. 5.0% / Biaya Layanan & Pembayaran) to the guest's checkout cart, plus recurring SaaS subscription revenue for Pro automation tools.

---

## 2. Subscription Tiers & Commercial Matrix

| Capability / Metric | **Starter Essential** | **Pro Operator** | **Agency Ultimate** | **AI Ultimate Agency** |
| :--- | :--- | :--- | :--- | :--- |
| **Monthly Subscription** | **Free / Rp 0** | **Rp 299.000 / mo** *(Rp 2.990.000 / yr)* | **Rp 699.000 / mo** *(Rp 6.990.000 / yr)* | **Rp 999.000 / mo** *(Rp 9.990.000 / yr)* |
| **Operator Commission Cut** | **0.0% (100% Net to Operator)** | **0.0% (100% Net to Operator)** | **0.0% (100% Net to Operator)** | **0.0% (100% Net to Operator)** |
| **Guest Service Fee** | **5.0%** (Paid by Guest at Checkout) | **5.0%** (Paid by Guest at Checkout) | **0.0%** (Direct BYO Gateway Settlement) | **0.0%** (Direct BYO Gateway Settlement) |
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

## 3. Financial Mechanics & Settlement Workflows

### How the Money Flows on a Booking (e.g. Rp 1.000.000 Tour Package via QRIS):

```text
===========================================================
 GUEST CHECKOUT CART BREAKDOWN:
-----------------------------------------------------------
 Nusa Penida Manta & Snorkel Safari:   Rp  1.000.000
 Guest Service & Processing Fee (5%):   Rp     50.000
-----------------------------------------------------------
 Total Paid by Guest via DOKU:          Rp  1.050.000
===========================================================

 AUTOMATED SETTLEMENT SPLIT:
-----------------------------------------------------------
 🟢 Operator Wallet (Available Escrow): Rp  1.000.000 (100% OF LISTED PRICE)
 🟣 Platform Revenue:                   Rp     50.000
 💳 DOKU Bank QRIS Cost (0.7%):       - Rp      7.350 (Covered by Platform)
-----------------------------------------------------------
 🚀 Platform Net Profit:                Rp     42.650 per transaction!
```

---

## 4. Why This Model Succeeds in Indonesia

1. **Zero Resistance from Tour Operators**:
   * Tour operators and agencies are thrilled because they get **100% of their requested price** deposited into their wallet.
   * Zero commission means zero hesitation to list all their trips and promote their official booking link.
2. **Unlimited Team Seats on All Plans**:
   * Indonesian agencies rely on WhatsApp reservation teams, tour guides, and freelance coordinators. Unrestricted seats ensure full staff adoption.
3. **Guest Cultural Acceptance**:
   * Travelers in Indonesia (and foreign tourists) are accustomed to a standard 5% online booking and processing fee (standard across Tiket.com, Traveloka, Loket.com).
4. **Predictable SaaS Revenue**:
   * Active operators happily pay `Rp 299.000/mo` (or `Rp 2.990.000/yr`) for Google Calendar live sync, WhatsApp automated dispatch, and CRM client tracking.

---

## 5. Formalized Financial Edge Cases, Risk Allocation & Settlement Rules

### 1. Refund After Payout (Clawback & Negative Balance)
- **Mechanism**: When a guest refund or chargeback occurs after funds have already been paid out to the operator, a `RefundDeduction` debit is logged against the operator's wallet.
- **Negative Balance Handling**: If cleared funds are insufficient, the wallet balance goes negative (`available_balance < 0`).
- **Auto-Recovery**: 100% of incoming booking earnings are automatically diverted towards liquidating negative balances before new payouts can be requested. Payouts are locked until `available_balance >= Rp 50.000`.

### 2. Uncollectible Negative Balance & Legal Debt Policy
- **30-Day Auto-Repayment Notice**: Negative balances unresolved after 30 days trigger automated email/WhatsApp demand notices and billing of the operator's stored card/bank method.
- **Financial Loss Provision**: EMVI Platform immediately makes the guest whole from platform reserves to preserve consumer trust. EMVI holds legal debt recourse against the operator entity under the Platform Merchant Agreement.

### 3. Chargebacks & Fraud Disputes
- **Dispute Reserve Hold**: Receiving a DOKU chargeback alert places the dispute amount + non-refundable bank chargeback dispute fee (Rp 150.000) on temporary hold (`DisputeHold`).
- **Dispute Resolution**:
  - **Won**: The dispute hold is released back to the operator's wallet.
  - **Lost**: The dispute amount and dispute fee are permanently debited from the operator's wallet.

### 4. Payment Failure After Reservation Creation
- **Mechanism**: 30-minute hold reservations (`#RSV-XXXX`) created without payment completion automatically expire via background worker (`platform:expire-holds`).
- **Ledger Impact**: Zero ledger entries are written for unpaid/expired reservations. Inventory capacity is released back to the package/product pool.

### 5. Partial Refunds
- **Mechanism**: Initiating a partial refund (e.g. 50% for partial itinerary cancellation) logs a `RefundDeduction` debit for the exact partial amount via `WalletService::processPartialRefund()`.
- **Fee Split**: On guest-initiated partial refunds within policy, platform retains original service fee proportion; on operator-fault partial refunds, fee is debited from operator.

### 6. Operator Cancellation (Fault / Weather / Capacity Failure)
- **Guest Protection Guarantee**: Guest receives a 100% full refund including the 5% Guest Service Fee.
- **Operator Liability**: Operator wallet is debited for the full refund amount plus the 5% Guest Service Fee so platform revenue is protected against operator operational failures.

### 7. Gateway Processing Fee Treatment on Refunds
- **Guest-Initiated (Within Policy)**: DOKU gateway fees (e.g. 0.7% QRIS) incurred during initial payment are absorbed by platform operating margin.
- **Operator-Initiated**: Operator wallet is debited for non-refundable gateway payment processing fees.

### 8. Payout Timing & Maturation Schedule
- **Departure Maturation (T+0)**: Escrow funds transition from `PendingEscrow` to `Cleared` on the trip departure date (`requested_date <= now()`).
- **Scheduled Escrow Release**: Automated daily cron (`platform:release-escrows` at 00:05 AM WIB) processes matured escrows.
- **Payout Processing Window**: Payout requests submitted before 12:00 PM WIB are disbursed within 1 business day (T+1).

### 9. Ledger Reconciliation (DOKU vs. EMVI)
- **Audit Matching**: Daily automated reconciliation (`platform:reconcile-doku`) cross-checks DOKU bank settlement records against internal `Payment` `gateway_ref` invoice numbers and `WalletTransaction` gross/net entries.
- **Discrepancy Alerts**: Mismatches trigger immediate alerts in `/admin/platform` and system log files.

### 10. Financial Loss Allocation Matrix

| Risk Event | Guest Status | Operator Impact | EMVI Platform Impact |
| :--- | :--- | :--- | :--- |
| **Guest Cancellation (In Policy)** | 100% Refund | 0 Escrow Earned | Absorbs gateway fee from margin |
| **Guest Cancellation (Late / No Show)** | 0% Refund | 100% Net Payout Retained | Retains 5% Guest Service Fee |
| **Operator Cancellation** | 100% Refund (Incl. Fee) | Wallet Debited for Fee Loss | Made whole via Operator Wallet Debit |
| **Chargeback Fraud (Lost)** | Refunded by Issuing Bank | Wallet Debited + Dispute Fee | Absorbs loss if operator insolvent |
| **Uncollectible Negative Balance** | Made Whole Immediately | Debt Owed (30-Day Collections) | Loss provisioned, legal debt recovery |

---

## 6. High-Ticket Bookings & BYO Gateway Fee Disclosures

### 1. High-Ticket Booking Guest Service Fee Cap (e.g. Rp 100.000.000 Bookings)
- **Problem Avoided**: On luxury private yacht charters or multi-day expedition bookings valued at `Rp 100.000.000`, a standard 5.0% fee equals `Rp 5.000.000`, which creates high checkout friction and cart abandonment.
- **Capped Fee Formula**:
  $$\text{Guest Service Fee} = \min\left(5.0\% \times P_{list},\; \text{Rp 250.000}\right)$$
- **Impact & Economics**:
  - **Rp 1.000.000 Booking**: 5% = `Rp 50.000` (Guest pays **Rp 50.000** fee).
  - **Rp 10.000.000 Booking**: 5% = `Rp 500.000` $\rightarrow$ **Capped at Rp 250.000**.
  - **Rp 100.000.000 Booking**: 5% = `Rp 5.000.000` $\rightarrow$ **Capped at Rp 250.000**!
- **Benefits**: Converts high-ticket luxury bookings seamlessly while providing EMVI platform `Rp 250.000` revenue per transaction (more than sufficient to cover flat bank processing costs).

### 2. Payment Gateway (PG) Fee Allocation in BYO Gateway Mode
- **BYO Custom Merchant Keys** (*Agency Ultimate* & *AI Ultimate Agency*):
  - **Who Pays Payment Gateway Fees?**: When an operator connects their own DOKU, Midtrans, or Xendit merchant keys, funds flow **directly from the bank into the operator's merchant bank account**.
  - Payment Gateway transaction fees (e.g. 0.7% QRIS, 2.9% Credit Cards) are billed **directly by DOKU to the operator's merchant account** under their direct merchant agreement.
  - EMVI Platform does **not touch transaction funds, does not collect a guest service fee (0.0%), and does not pay payment gateway fees**.
- **Shared Platform Gateway Mode** (*Starter Essential* & *Pro Operator*):
  - Payments route through EMVI's shared DOKU account. Guests pay the 5.0% Guest Service Fee at checkout.
  - EMVI pays DOKU's ~0.7% to 2.9% PG fees out of the 5.0% guest fee, retaining **~4.3% net margin**.

---

## 7. Subscription Lifecycle & Operational Financial Rules

### 1. Prorated Subscription Upgrades & Billing Cycles
- **Prorated Upgrades**: Mid-cycle upgrades (e.g. *Pro Operator* $\rightarrow$ *Agency Ultimate*) calculate the exact unused prorated value of the remaining days on the current subscription and apply it as a credit towards the new plan fee.
- **Annual Discount**: 2 months free equivalent applied on annual billing (`Price Monthly x 10`).

### 2. Failed Renewal Grace Period & Fallback Engine
- **3-Day Grace Period**: A failed recurring subscription payment initiates a **3-day grace period**. The operator retains full access to their tier features while warning banners and automated WhatsApp/email notifications request updated billing details.
- **Day 4 Fallback Execution**: On Day 4, if payment remains uncollected, the account automatically falls back to **Starter Essential (Free)**:
  - Custom domain (`yourbrand.com`) pauses and falls back to `slug.travelengine.online`.
  - Active packages above 5 are set to *Draft*.
  - Guest service fee returns to 5.0% pass-through.

### 3. Currency & FX Settlement Guarantee
- **IDR Currency Standardization**: All package pricing, guest checkouts, ledger entries, and bank payouts operate strictly in **Indonesian Rupiah (IDR / Rp)**.
- **Zero FX Risk**: Guaranteed 100% net IDR payout ($P_{list}$) deposited into operator bank accounts without FX volatility.

### 4. Payout Transfer Fee & Minimum Threshold Policy
- **Minimum Payout**: **Rp 50.000** minimum threshold for manual payout requests.
- **BI-FAST Disbursement Fee Allocation**:
  - **Payouts $\ge$ Rp 500.000**: **100% Free** (bank transfer fee absorbed by platform margin).
  - **Payouts $<$ Rp 500.000**: Flat **Rp 2.500** bank transfer fee debited from payout amount.

### 5. Promo Code Cost Allocation Rules
- **Platform Promo Codes** (Admin-created, e.g. `EMVI2026`): 100% absorbed by EMVI platform fee reserves. Operator receives full 100% net listed price.
- **Operator Promo Codes** (Operator-created, e.g. `SUMMER50K`): Absorbed by the operator. Net earnings and wallet credit reflect the discounted price.

