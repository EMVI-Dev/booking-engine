# Plans and pricing

_Last reviewed: 2026-10-02_

This is the one home for plan names, prices, limits and the guest service fee. Money handling (ledger, escrow, payouts, refunds) is in [money-rules-and-settlement.md](money-rules-and-settlement.md). The seeded values live in `Plan::seedDefaultPlans()` and must match this page.

## Core principle

Following the model used by FareHarbor, Loket.com and Megatix:

> **Operators keep 100% of their listed price.** The platform earns a transparent **Guest Service Fee** added at checkout (Biaya Layanan & Pembayaran), plus recurring subscriptions for the tools.

There is **no bring-your-own gateway** on any plan. Every booking checks out through EMVI's single DOKU merchant account, and EMVI pays DOKU's fees from the guest service fee.

## Plans (V1)

| | **Starter** (`starter`) | **Growth** (`growth`) | **Agency** (`agency`) |
| :--- | :--- | :--- | :--- |
| Monthly | Free | Rp 299.000 | Rp 799.000 |
| Yearly | Free | Rp 2.990.000 | Rp 7.990.000 |
| Who | Solo freelance guide | Freelancer with more tools, or a small group | Small to mid travel agency |
| Trips and activities (live) | 5 | 25 | Unlimited |
| People on the team | You and 1 helper | Unlimited | Unlimited |
| Operator commission | 0% | 0% | 0% |
| Guest service fee | 5% (cap Rp 250.000) | 5% (cap Rp 250.000) | 5% (cap Rp 250.000) |
| Shop address | `slug.travelengine.id` | `slug.travelengine.id` | slug **plus own domain** |
| Google Calendar live iCal, Guest CRM and lifetime spend, Meta/GA4 tracking, WhatsApp dispatch, review requests | | ✓ | ✓ |
| White-label (no platform name), capacity heatmap, AI Search (`/llms.txt`), Google reviews on the shop, priority support | | | ✓ |
| Payment rails | EMVI DOKU wallet | EMVI DOKU wallet | EMVI DOKU wallet |

Feature labels shown to operators come from `Plan::featureCatalog()` and must match the desk menu (see `.ai/rules/models.md`). **Enterprise is not in V1** ([roadmap V2](../product/roadmap-v2.md)).

The annual price is about two months free (`monthly × 10`).

## Guest service fee

$$\text{Guest Service Fee} = \min(5\% \times \text{subtotal},\ \text{Rp 250.000})$$

The subtotal is the listed price × pax, before any operator promo code (`BookingPricingService::quote()`).

The rate and cap are platform settings (Admin → Settings), seeded as 5% and Rp 250.000.

| Booking | 5% | Guest pays |
| :--- | :--- | :--- |
| Rp 1.000.000 | Rp 50.000 | Rp 50.000 |
| Rp 10.000.000 | Rp 500.000 | **Rp 250.000** (cap) |
| Rp 100.000.000 | Rp 5.000.000 | **Rp 250.000** (cap) |

The cap keeps high-ticket charters bookable, while Rp 250.000 still covers flat bank costs.

### Worked example (Rp 1.000.000 tour paid by QRIS)

| Line | Amount |
| :--- | :--- |
| Tour price | Rp 1.000.000 |
| Guest service fee (5%) | Rp 50.000 |
| **Guest pays through DOKU** | **Rp 1.050.000** |
| Operator wallet (escrow until trip day) | Rp 1.000.000 (100% of the listed price) |
| Platform revenue | Rp 50.000 |
| DOKU QRIS cost (0.7% of Rp 1.050.000), paid by EMVI | − Rp 7.350 |
| EMVI net | Rp 42.650 |

On small virtual-account or card tickets the margin is thin. DOKU's flat VA fee of Rp 4.000–4.500, or 2.8% + Rp 2.000 on cards, plus PPN can use up most of the 5%. Current DOKU rates are in [scope and features](../product/scope-and-features.md#5-payment-gateway-engine-doku-hosted-checkout-exclusive).

## Promo codes: who pays
- **Platform promo codes** (admin, subscriptions only) discount the operator's subscription invoice. They never touch guest checkouts.
- **Operator promo codes** (storefront) are paid by the operator. The operator's earning is the discounted price. The guest fee is still calculated on the full subtotal.

## Subscription lifecycle (summary)
- **Upgrade:** takes effect immediately. Unused days on the current plan are credited, linearly prorated.
- **Downgrade:** scheduled for the end of the period.
- **Failed renewal:** 3 days of grace with warnings, then the shop returns to Starter on day 4.
  - The own domain pauses; the shop falls back to the slug.
  - Live listings over the Starter limit go to draft.
- Exact rules and the ledger side: [money-rules-and-settlement.md](money-rules-and-settlement.md).

## Why this works in Indonesia
1. **Operators:** keep their full price, so there is no hesitation to publish every trip and share the booking link.
2. **Guests:** a 5% booking and processing fee is familiar from Tiket.com, Traveloka and Loket.com.
3. **Teams:** WhatsApp reservation teams and freelance coordinators get unlimited seats from Growth up.
4. **Recurring revenue:** operators pay for tools they use daily (calendar sync, CRM, WhatsApp dispatch).
