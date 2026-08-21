# Platform Commercial & Pricing Model Specification

This document defines the official commercial architecture, guest fee structure, payment processing pass-through rules, and operator disclosures for the Booking Engine Platform.

---

## 1. Executive Summary & Industry Standard

Following the industry model used by **FareHarbor (Booking.com)**, **Loket.com**, and **Megatix**:

> **Core Principle**: **Operators keep 100% of their listed tour package prices.** 
> The platform generates automated transaction revenue by adding a standard, transparent **Guest Service Fee** (e.g. 5.0% / Biaya Layanan & Pembayaran) to the guest's checkout cart, plus recurring SaaS subscription revenue for Pro automation tools.

---

## 2. Subscription Tiers & Commercial Matrix

| Capability / Metric | **Starter Essential** | **Pro Operator** | **Agency Ultimate** |
| :--- | :--- | :--- | :--- |
| **Monthly Subscription** | **Free / Rp 0** | **Rp 299.000 / mo** *(Rp 2.990.000 / yr)* | **Rp 699.000 / mo** *(Rp 6.990.000 / yr)* |
| **Operator Commission Cut** | **0.0% (100% Net to Operator)** | **0.0% (100% Net to Operator)** | **0.0% (100% Net to Operator)** |
| **Guest Service Fee** | **5.0%** (Paid by Guest at Checkout) | **5.0%** (Paid by Guest at Checkout) | **0.0%** (Direct BYO Gateway Settlement) |
| **Package Listings Limit** | Up to **5** Packages | Up to **25** Packages | **Unlimited Listings** |
| **Team Staff Seats** | **Unlimited Staff** | **Unlimited Staff** | **Unlimited Staff** |
| **Storefront Subdomain** | ✅ `slug.booking.emvi` | ✅ `slug.booking.emvi` | ✅ `slug.booking.emvi` |
| **Custom Domain (`yourbrand.com`)** | 🔒 *Gated* | 🔒 *Gated* | ✅ **Included with Auto-SSL** |
| **Google Calendar & Live iCal Feed** | 🔒 *Gated* | ✅ **Included** | ✅ **Included** |
| **Guest CRM Directory & LTV** | 🔒 *Gated* | ✅ **Included** | ✅ **Included** |
| **1-Click WhatsApp Dispatch Center** | 🔒 *Gated* | ✅ **Included** | ✅ **Included** |
| **Payment Gateway Credentials** | Shared Platform Gateway | Shared Platform Gateway | **BYO Custom Merchant Keys** |

---

## 3. Financial Mechanics & Settlement Workflows

### How the Money Flows on a Booking (e.g. Rp 1.000.000 Tour Package via QRIS):

```text
===========================================================
 GUEST CHECKOUT CART BREAKDOWN:
-----------------------------------------------------------
 Nusa Penida Manta & Snorkel Safari:   Rp  1.000.000
 Biaya Layanan & Pembayaran (5%):       Rp     50.000
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
   * Boat owners and agencies are thrilled because they get **100% of their requested price** deposited into their wallet.
   * Zero commission means zero hesitation to list all their trips and promote their official booking link.
2. **Unlimited Team Seats on All Plans**:
   * Indonesian agencies rely on WhatsApp reservation teams, boat captains, and freelance coordinators. Unrestricted seats ensure full staff adoption.
3. **Guest Cultural Acceptance**:
   * Travelers in Indonesia (and foreign tourists) are accustomed to a standard 5% online booking and processing fee (standard across Tiket.com, Traveloka, Loket.com).
4. **Predictable SaaS Revenue**:
   * Active operators happily pay `Rp 299.000/mo` (or `Rp 2.990.000/yr`) for Google Calendar live sync, WhatsApp automated dispatch, and CRM client tracking.
