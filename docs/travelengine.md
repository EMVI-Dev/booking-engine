# TravelEngine — Product Overview & Objectives

> **"What are you trying to achieve?"**  
> A focused overview of TravelEngine’s mission, audience, business model, operational capabilities, and technical architecture.

---

## 1. Vision & Core Mission

**TravelEngine** (developed by **EMVI Technologies**) is a dedicated booking, inventory, and operations engine designed specifically for **freelance tour guides, activity hosts, and small-to-mid travel agencies**.

The primary objective is to empower day-tour and activity providers to run an automated, direct-booking business on their own brand without paying punishing commissions to centralized online travel agencies (OTAs) or wrestling with bloated, legacy enterprise software.

Unlike traditional booking software that assumes hotel room nights or multi-day rental fleets, TravelEngine is purpose-built for **Single-Day Capacity Booking**:
- **Date-First Booking**: Guests select a single activity/departure date (`requested_date`).
- **Real-Time Capacity Protection**: Strict daily limits (`capacity_per_day`) shared across single activities and multi-activity packages prevent overbooking.
- **Independent Brand Destinations**: Each operator receives their own standalone booking storefront (`slug.travelengine.id` or custom domain `yourbrand.com`). There is no public marketplace or cross-agent directory pitting operators against each other on price.

---

## 2. Problems Solved

1. **WhatsApp & DM Booking Friction**:  
   Operators traditionally coordinate inquiries manually via WhatsApp, phone, or Instagram DMs, tracking slots on paper or spreadsheets. TravelEngine offers a **1-Click Direct Booking & Payment Link Generator** with an automated 30-minute inventory hold.
2. **Catalog & Inventory Complexity**:  
   Curated day-tour packages (`packages`) synchronize capacity with underlying standalone activities (`products`), ensuring inventory decrements accurately across bundled and unbundled offerings.
3. **Third-Party Vendor Disconnect**:  
   Operators often outsource portions of trips (boat charters, dive shops, transport). TravelEngine includes automated **Vendor Dispatch** that emails booking copies and manifests directly to suppliers upon guest payment.
4. **Disorganized Ground Operations**:  
   Replaces chaotic morning coordination with high-contrast, print-perfect A4 daily manifests complete with guide/driver sign-off blocks, visual capacity timelines, and monthly density heatmaps.

---

## 3. Commercial & Business Model

TravelEngine adopts the proven transaction model used by platforms like FareHarbor, Loket.com, and Megatix:

* **0% Commission on Operator Earnings**: Operators keep **100% of their listed price** deposited into their wallet escrow.
* **Transparent Guest Service Fee**: A standard **5% fee** (capped at Rp 250.000) is paid by guests at checkout. This fee covers payment processing costs (DOKU: QRIS, Virtual Accounts, Credit Cards) and funds platform escrow/settlement operations.
* **Tiered SaaS Subscriptions**:
  * **Starter (Free)**: For solo freelance guides (up to 5 trips/activities, custom subdomain, WhatsApp floating widget).
  * **Growth (Rp 299.000 / mo | Rp 2.990.000 / yr)**: Up to 25 trips, Google Calendar live iCal sync, Guest CRM & lifetime spend analytics, Meta/GA4 tracking, 1-click WhatsApp dispatch center, and automated review request emails.
  * **Agency (Rp 799.000 / mo | Rp 7.990.000 / yr)**: Unlimited listings, custom domains (`yourbrand.com`), full white-labeling (removes platform branding), operations capacity heatmap, Google reviews integration, and AI Search Discovery (`/llms.txt`).

---

## 4. Key Capabilities & Guest Lifecycle

* **Branded Storefront & Direct Checkout**: Mobile-optimized guest experience with instant capacity validation, DOKU Jokul hosted checkout, tokenized reservation URLs (`/reservations/{public_token}/...`), and digital e-tickets.
* **Guest CRM & Operations Hub**: Customer profiles tracking repeat bookings, tags, staff notes, and lifetime value (LTV).
* **WhatsApp Dispatch Center**: 1-click pre-formatted dispatch messages for payment hold recovery, e-voucher delivery, 24-hour departure reminders, and meeting point locations.
* **Immutable Double-Entry Ledger & Escrow**: Secure wallet escrow holds funds until the trip departure date before clearing for automated domestic bank payouts (T+3 settlement).
* **AI Search Optimization (`/llms.txt`)**: Agency tier serves curated Markdown feeds for ChatGPT, Claude, Perplexity, and Gemini search crawlers.

---

## 5. Technical Architecture

* **Framework & Core**: Laravel 13 on PHP 8.5, Livewire 4 Single-File Components (SFCs), Tailwind CSS v4, Alpine.js, and Blaze component rendering.
* **Multi-Tenant Host Architecture**: Single application codebase handling three host contexts:
  1. Platform apex (`travelengine.id` / `www`) — Marketing site, operator desk, auth, platform admin.
  2. Operator subdomains (`{slug}.travelengine.id`) — Branded guest storefronts.
  3. Custom domains (`yourbrand.com`) — Agency storefronts with automatic TLS certificate issuance via Caddy.
* **Quality & Test Standards**: Pest 5 test suite with 100% passing automated feature and unit tests; code formatted via Laravel Pint; ULIDs for database primary keys.

---

## 6. Deliberate Scope Boundaries (Out of V1)

To maintain focus and simplicity, the platform explicitly excludes:
- Multi-day check-in $\rightarrow$ check-out date ranges (hotel rooms, multi-day vehicle rentals).
- Cross-agent public marketplace or price comparison directories.
- In-app live chat (WhatsApp floating widget handles direct inquiries).
- In-app guest reviews desk (relies on verified Google Reviews and external review platforms).
- Bring-your-own payment gateway (all transactions run through EMVI's unified DOKU payment engine).
- Enterprise tier / embeddable widgets (reserved for V2).

---

## 7. Related Documentation

- [`scope_and_features.md`](scope_and_features.md) — Comprehensive product rules and current feature specifications.
- [`stack.md`](stack.md) — Technology stack, server architecture, and hosting setup.
- [`platform_commercial_and_pricing_model.md`](platform_commercial_and_pricing_model.md) — Pricing tiers, guest fee economics, and financial edge cases.
- [`emvi_v1_money_rules_and_settlement_spec.md`](emvi_v1_money_rules_and_settlement_spec.md) — Authoritative double-entry ledger, escrow, and payout rules.
