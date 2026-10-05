# TravelEngine: start here

_Last reviewed: 2026-10-05_

**This is the one file to read first.** It says what TravelEngine is and what ships, and it links every other doc. Point any new tool, teammate or AI agent here.

- Each fact has one home. This page summarises and links; it does not repeat prices, money rules or hosting details.
- If a doc and the code disagree, the code wins. Fix the doc in the same change.

---

## 1. What TravelEngine is

TravelEngine, built by **EMVI Technologies**, is a booking, inventory and operations platform for **freelance tour guides, activity hosts and small-to-mid travel agencies** in Indonesia.

Each operator gets a simple website with booking and payment built in, on their own address (`{slug}.travelengine.id`, or their own domain on Agency). There is no marketplace and no price comparison between operators.

It is built for **single-day capacity booking**:

- Guests pick one date (`requested_date`). There are no check-in and check-out ranges.
- Each activity has a daily limit (`capacity_per_day`). Packages share the capacity of the activities inside them, so nothing is overbooked.

**Design rule:** most users run the business alone. Everything is set once and then keeps itself right. Nothing needs regular writing, and empty sections never show.

## 2. Problems it solves

1. **Booking by WhatsApp and DMs.** Operators create a booking and payment link in one click, with a 30-minute hold on the seats.
2. **Bundled inventory.** Packages and single activities share daily capacity, sold together or separately.
3. **Outsourced parts of a trip.** Suppliers (boats, dive shops, transport) get a booking copy automatically when the guest pays.
4. **Messy mornings.** Printable A4 daily manifests, a weekly capacity timeline and a monthly heatmap.
5. **No website.** The booking page is the operator's website: trips, gallery, FAQ, contact form and Google listing.

## 3. How it makes money

- **Operators keep 100% of their listed price.**
- **Guests pay a service fee** at checkout. It covers payment costs, escrow and payouts.
- **Subscriptions** unlock tools:
  - **Starter** (free) for a solo guide;
  - **Growth** for a freelancer with more tools, or a small team;
  - **Agency** for a travel agency: own domain, white-label, Google listing and AI search feed.
- All payments run through EMVI's DOKU account. Operators can't bring their own payment gateway.

Prices, limits, the fee rate and cap, and what each plan unlocks: [plans and pricing](../commercial/plans-and-pricing.md).

## 4. What ships in V1

The full rules for every area are in [scope and features](scope-and-features.md). Each line below links to the doc that owns the detail.

| Area | What it does | Detail |
| :--- | :--- | :--- |
| **Catalog** | Tour packages (itinerary, inclusions, gallery) built from single activities with daily capacity. Activities can be sold alone or only inside packages. | [Scope §1](scope-and-features.md#1-catalog--inventory-management) |
| **Vendors** | Supplier directory; activities can be linked to a supplier, who is emailed booking and cancellation copies with a secure dispatch view. | [Vendor dispatch](../features/vendor-dispatch.md) |
| **Bookings and checkout** | Date picker with live capacity, 30-minute hold, DOKU hosted checkout, e-ticket, Find Booking. Guest links use a private token, never the record id. | [Scope §3](scope-and-features.md#3-branded-storefront--guest-experience) |
| **Operator desk** | Dashboard, reservations, manual booking link, calendar, manifests, heatmap (Agency), guest CRM (Growth+), WhatsApp dispatch messages, enquiries inbox. | [Scope §2](scope-and-features.md#2-operator-portal--daily-operations) |
| **Coupons and reports** | Guest promo codes with their own report and CSV export, kept separate from subscription promos. | [Coupons, billing and CRM](../features/operator-portal-coupons-billing.md) |
| **Team and sign-in** | Roles: owner, admin, reservations, finance. Sign-in on the main domain hands over to the shop's own address. Email verification, passkeys. | [Coupons, billing and CRM §2](../features/operator-portal-coupons-billing.md) |
| **Storefront website** | Home and catalog pages, plus Gallery, FAQ and Contact pages (each only when it has content), cookie notice, WhatsApp button, SEO and JSON-LD, sitemap. | [Storefront website](../features/storefront-website.md) |
| **Reviews and Google listing** | A review link on every plan; on Agency, a connected Google listing shown with Google's free map card. Post-trip review emails (Growth+). | [Scope §3 Reviews](scope-and-features.md#3-branded-storefront--guest-experience), [Integrations](../engineering/integrations.md) |
| **Own domain and white-label** | Agency: own domain added through Laravel Cloud; the platform name is hidden on guest pages and emails. | [Custom domains](../features/custom-domains.md), [Scope §3](scope-and-features.md#3-branded-storefront--guest-experience) |
| **AI search feed** | Agency: `/llms.txt` and `/llms-full.txt` for AI search engines. | [Scope §3](scope-and-features.md#3-branded-storefront--guest-experience) |
| **Money** | Append-only wallet ledger, escrow until the trip day (Bali time), payouts after DOKU settlement, refunds and disputes. | [Money rules](../commercial/money-rules-and-settlement.md) |
| **Subscriptions and billing** | Plan switch with proration, renewals, invoices, payout bank account, subscription promo codes. | [Plans and pricing](../commercial/plans-and-pricing.md), [Coupons, billing and CRM §3](../features/operator-portal-coupons-billing.md) |
| **Emails and alerts** | Guest emails (hold, confirmed, reminder, review), operator new-booking email, vendor emails, Slack alerts to the platform team. | [Scope §6](scope-and-features.md#6-notifications--communication) |
| **Platform admin** | Operators, plans, subscriptions, announcements, platform coupons, payouts, platform settings, admins, audit log; admins can manage a shop for up to 2 hours. | [Scope §7](scope-and-features.md#7-platform-admin--demo-shop) |
| **Demo shop** | A public demo operator with checkout switched off, reset every night. | [Scope §7](scope-and-features.md#7-platform-admin--demo-shop), [Production launch](../engineering/production-launch.md) |

## 5. Not in V1

- Multi-day date ranges (hotel rooms, multi-day rentals).
- A marketplace or price comparison between operators.
- In-app live chat (the WhatsApp button covers this).
- In-app guest reviews (Google and other review sites cover this).
- Bring-your-own payment gateway.
- Multi-currency and translations.
- Enterprise plan and embeddable widgets.

Planned later, and not to be built until that file says so: [roadmap V2](roadmap-v2.md).

## 6. How it is built

Laravel 13 on PHP 8.5, Livewire 4 single-file components (no Volt), Tailwind CSS v4, Alpine.js and Blaze. MySQL in production (MariaDB-compatible), Cloudflare R2 for media, hosted on Laravel Cloud. The whole app runs on Bali time (WITA). Tests use Pest 5 on SQLite and MariaDB, with Larastan and Pint. Primary keys are ULIDs.

One app serves three kinds of address: the platform domain (marketing, operator desk, sign-in, admin), operator subdomains, and Agency custom domains.

Hosting, queues, scheduler and where things live in the code: [stack and hosting](../engineering/stack-and-hosting.md).

---

## 7. Documentation map

Every doc in `docs/`, by who reads it.

**Product: what we build**

| Doc | What it covers |
| :--- | :--- |
| [product/overview.md](overview.md) | This page: the entry point |
| [product/scope-and-features.md](scope-and-features.md) | The main V1 spec: every product rule and shipped feature |
| [product/roadmap-v2.md](roadmap-v2.md) | Not in the product yet. Nothing ships until this file says so |

**Commercial: plans and money**

| Doc | What it covers |
| :--- | :--- |
| [commercial/plans-and-pricing.md](../commercial/plans-and-pricing.md) | The only home for plan names, prices, limits and the guest service fee |
| [commercial/money-rules-and-settlement.md](../commercial/money-rules-and-settlement.md) | The only home for money rules: ledger, escrow, payouts, refunds, disputes, fees, and what the code automates today |

**Features: how specific areas work**

| Doc | What it covers |
| :--- | :--- |
| [features/storefront-website.md](../features/storefront-website.md) | Gallery, FAQ and Contact pages, enquiries, cookie notice, Google listing, page SEO, and the UI brief for Antigravity |
| [features/operator-portal-coupons-billing.md](../features/operator-portal-coupons-billing.md) | Guest coupons and reports, sign-in handoff, billing and invoices, guest CRM |
| [features/vendor-dispatch.md](../features/vendor-dispatch.md) | Suppliers and automatic booking dispatch |
| [features/custom-domains.md](../features/custom-domains.md) | Shop addresses (slugs) and Agency custom domains on Laravel Cloud |

**Engineering: how it is built and run**

| Doc | What it covers |
| :--- | :--- |
| [engineering/stack-and-hosting.md](../engineering/stack-and-hosting.md) | The only home for stack, hosts, Laravel Cloud, queues, scheduler, time zone and code layout |
| [engineering/integrations.md](../engineering/integrations.md) | Every outside service (DOKU, Laravel Cloud, Google, Slack, R2): its client class, settings and failure behaviour |
| [engineering/production-launch.md](../engineering/production-launch.md) | Fresh production checklist: environment variables, Cloud settings, first deploy, smoke test, going live with DOKU |

**Audits: dated snapshots, not specs**

| Doc | What it covers |
| :--- | :--- |
| [audits/2026-10-storefront-and-landing-audit.md](../audits/2026-10-storefront-and-landing-audit.md) | Security pass, guest storefront and landing page, with fixes (Oct 2026) |
| [audits/2026-10-backend-audit.md](../audits/2026-10-backend-audit.md) | Backend, DRY, security, admin and MVP audit with fix log (Oct 2026) |
| [audits/2026-09-storefront-pre-prod-audit.md](../audits/2026-09-storefront-pre-prod-audit.md) | Guest storefront pre-production review (Sep 2026) |
| [audits/archive/](../audits/archive/) | Superseded audits, kept for history |

## 8. For AI agents and contributors

- **Who does what.**
  - **Claude:** backend: services, models, migrations, money, integrations, tests.
  - **Antigravity:** UI and UX: Blade views, Livewire markup, Alpine and Tailwind.
  - Role files: `.agents/agents/backend-claude` and `.agents/agents/ui-gemini`.
- **Code rules.**
  - Reusable business logic lives in one service class per job in `app/Services/`; views and components call services.
  - Every outside service has one client class in `app/Services/Integrations/`.
- **Agent instructions.**
  - `CLAUDE.md` and `AGENTS.md` hold the Laravel Boost guidelines.
  - `.ai/rules/index.md` maps file paths to project rules. Read the matching rule file before editing.
- **Skills.**
  - Claude Code: `.claude/skills/`. Antigravity: `.agents/skills/`.
  - Includes `design-taste-frontend`; our stack overrides its defaults (rule in `.ai/rules/views.md`).
- **Keeping docs right.** A change to plans, money, hosting, integrations or a feature updates its doc in the same change, and bumps "Last reviewed". New docs get a row in the map above. Rules: `.ai/rules/docs.md`.
