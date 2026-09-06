# Booking Engine Platform — V1 Spec (Rev. 21)

## Vision & Audience

A focused, single-day capacity booking and inventory engine built specifically for **Freelance Guides, Activity Hosts, and Travel Agencies**.

Unlike complex legacy software that assumes enterprise hotel or multi-day vehicle rental operations, this platform is tailored to **Daily Capacity Booking**:
- Each operator gets their own branded booking storefront (default subdomain `slug.booking.emvi`, upgradeable to custom domain `yourbrand.com` on Agency).
- No cross-agent marketplace search/discovery — the root platform domain handles marketing and authentication; each operator's storefront is an independent booking destination.
- Single calendar date selection (`requested_date`) and daily capacity limits per activity (`capacity_per_day`).

---

## Core Domain Entities

| Entity | DB Table | Concept & Purpose |
| :--- | :--- | :--- |
| **Tour Packages** | `packages` | Full curated day tours, excursions, and multi-activity packages with chronological itineraries, inclusions, and exclusions. Bundles 1+ single activities. |
| **Single Activities** | `products` | Standalone bookable activities, workshop sessions, day passes, guide hire, or transport services. Can be sold standalone or linked into packages. |
| **Reservations** | `reservations` | Single-date booking records (`#RSV-XXXX`) tracking guest info, selected date, pax count, payment status, and snapshot-frozen terms. Guest-facing URLs use `public_token`, never the record id. |

---

## V1 Scope (Confirmed)

### 1. Catalog & Inventory Management
- **Tour Packages (`packages`)**:
    - Build rich multi-activity packages with cover photos, gallery, duration, highlights, line-by-line chronological itinerary schedule, inclusions, and exclusions.
    - Synchronized capacity: Links to underlying Single Activities to automatically deduct and synchronize daily capacity.
- **Single Activities (`products`)**:
    - Define standalone bookable items with daily capacity limits (`capacity_per_day`), pricing, category classifications (*Day Tour / Trip, Workshop & Class, Activity Session, Guide Hire, Ticket & Admission, Day Transport, Add-on Service*), and toggleable storefront standalone availability (`sellable_standalone`).

### 2. Operator Portal & Daily Operations
- **1-Click Direct Booking & Payment Link Generator**:
    - Operators can manually create a booking on behalf of guests from WhatsApp, phone, Instagram DM, or walk-ins.
    - Generates a 30-minute hold reservation (`#RSV-XXXX`), auto-creates/indexes the guest in CRM, and provides a 1-click **"Copy Payment Link"** or **"Send via WhatsApp"**.
- **Operations Calendar & Resource Matrix**:
    - **Resource Timeline**: Weekly matrix of daily capacity usage across all activities.
    - **Capacity Density Heatmap**: Monthly color-coded density calendar showing sold-out, high-occupancy, and open days (Agency).
    - **Print-Perfect Daily Manifest**: High-contrast A4 printable run-sheet with check-in pen tick-boxes (`[ ] Check`), passenger details, notes, and a 3-part ground crew sign-off block (Tour Guide, Driver/Vehicle ID, Dispatch Officer).
- **Streamlined Navigation & Sidebar**:
    - Clean hierarchy: *Dashboard, Reservations, Calendar, Tour Packages, Single Activities, Coupons & Discounts, Storefront Settings, Subscription & Billing*.
- **Native Mobile Navigation Bar & Mobile Card Lists (`md:hidden`)**:
    - Sticky glassmorphism mobile bottom navigation bar (`lg:hidden fixed bottom-0 left-0 right-0 z-40 h-16`) for 1-thumb operations.
    - All data tables convert into responsive mobile cards on smartphone screens (`< 768px`).

### 3. Branded Storefront & Guest Experience
- **Subdomain & Custom Domain Routing**:
    - Default: `slug.booking.emvi` (DNS only to Lightsail). Platform apex `booking.emvi` is Cloudflare-proxied.
    - Custom Domain (Agency): CNAME to a grey hostname (`cname.booking.emvi` or the operator slug), or A / AAAA the apex at the Lightsail IP. Caddy issues the guest padlock. Do not CNAME at the orange apex.
- **Segmented Storefront Navigation**:
    - Direct access to *Catalog (Home)*, *Tour Packages*, *Single Activities*, and *Terms & Policies*.
- **Direct Checkout & 30-Minute Hold Recovery**:
    - Date picker with real-time capacity validation and instant 30-minute hold countdown.
    - Payment resumption, receipt, and e-ticket use `/reservations/{public_token}/…`. The reservation record id is never exposed to guests.
- **Tier-Gated AI Search Discovery (`/llms.txt`)**:
    - Agency exclusive.
    - Automatically serves structured `/llms.txt` and `/llms-full.txt` Markdown catalog feeds for ChatGPT, Perplexity, Claude, and Gemini crawlers.
- **Google Search Console (GSC) Domain Ownership**:
    - One-click Google site verification tag injection in storefront `<head>`.
- **WhatsApp Floating Widget**:
    - Bottom-right floating chat with customizable pre-filled inquiry messages.
- **Agency White-Label (hide-name)**:
    - Agency (`remove_branding`) hides the platform name on every guest-facing channel. Starter and Growth still credit the platform.
    - Storefront: tab titles, `og:site_name`, generator meta, home JSON-LD platform block, and footer “Powered by”.
    - Guest mail: inbox **From name** is the operator (sending address stays the platform mailbox for SPF/DKIM). Reply-To is the operator’s booking email.
    - Guest mail bodies: confirmation footer and review “via {platform}” line.
    - Confirmation `.ics` `PRODID` uses the operator name.
    - WhatsApp dispatch copy already uses the operator name.
    - **Not white-labeled**: subscription / billing mail (that is EMVI’s bill). Operator dashboard, auth, and the marketing site stay platform-branded.

### 4. Commercial & Subscription Model (3 Tiers)

Public names are **Starter**, **Growth**, and **Agency** (same as the slugs). **Enterprise is not in V1** — do not seed or show it (see `v2.md`).

Guest service fee is **5% on every plan**, capped at **Rp 250.000**. Operator gets 100% of the listed price. Subscription buys features. It does not waive checkout fees or move settlement onto the operator's merchant account. No BYO gateway.

- **Starter** (`starter` — Free):
    - Freelance tour guide. Up to 5 trips and activities together, you and 1 helper, custom subdomain, and WhatsApp floating widget. Platform name stays on the storefront and in guest-mail From.
- **Growth** (`growth` — Rp 299.000 / mo | Rp 2.990.000 / yr):
    - Freelance with more tools, or a small group selling together. Up to 25 trips and activities, unlimited people on your team. Unlocks **Google Calendar 1-Click & Live iCal Feed Sync**, **Guest Directory CRM & Lifetime Spend Analytics**, **Meta Pixel & GA4 ROAS tracking**, **1-Click WhatsApp Dispatch Center**, and **automated review requests**.
- **Agency** (`agency` — Rp 799.000 / mo | Rp 7.990.000 / yr):
    - Small to mid travel agency. Unlimited listings, **custom domain**, **full white-label**, **capacity heatmap**, **AI Search Discovery (`/llms.txt`)**, and priority support. Checkout, escrow, and payouts stay on the EMVI DOKU wallet.
- **Operator Plan & Billing Portal (`/settings/plan`)**:
    - Unified subscription management with interactive tier switcher, proration calculations, auto-renew controls, and invoice receipts.
- **Platform Coupon Intelligence & Auto-Broadcast System**:
    - Platform coupons supporting `first_purchase`, `lifetime`, and volume threshold eligibility rules (`min_confirmed_transactions`).
    - Automated scheduled daily broadcast (`coupons:broadcast`) notifying eligible operators via dashboard announcements.

### 5. Payment Gateway Engine (DOKU Hosted Checkout Exclusive)
- **Live DOKU Jokul Hosted Checkout**:
    - Full integration with `POST /checkout/v1/payment` using HMAC-SHA256 signature authorization for Virtual Accounts (BCA, Mandiri, BRI, BNI), QRIS, Credit Cards (3D Secure OTP), and E-Wallets.
- **Zero-Config Offline Simulation**:
    - Built-in payment simulator (`/checkout/simulate`) for instant local testing and offline demos.
- **Live Status Inquiry & Auto-Sync**:
    - Real-time payment verification (`GET /orders/v1/status/{invoice}`) upon guest return and via 1-click **"Sync with DOKU"**.
- **Automated DOKU BI-FAST Payout Engine**:
    - Operator payout requests up to Rp 10.000.000 disbursed via DOKU Fund Transfer API (`POST /disbursement/v1/transfer`) in under 3 seconds.
- **Single platform merchant account**:
    - Every plan, including Agency, checks out through EMVI's DOKU wallet. Guest service fee funds gateway costs, escrow, and payouts. Agency does not connect a private merchant account.

### 6. Notifications & Communication
- **Guest email lifecycle** (From name is the operator on Agency; platform on Starter/Growth):
    - `GuestBookingCreatedMail`: Hold confirmation with countdown and payment link.
    - `GuestBookingConfirmedMail`: Verified payment receipt with digital e-ticket voucher.
    - `GuestDepartureReminderMail`: Day-before trip reminder.
    - `GuestReviewRequestMail`: Post-trip review request (Growth and Agency).
- **Operator notification (`OperatorNewBookingNotificationMail`)**:
    - Instant email alert on paid booking capture. Agency uses the operator as the From name. (`AgentNewBookingNotificationMail` is a deprecated alias.)
- **1-Click WhatsApp Dispatch Center**:
    - Pre-formatted messages for Payment Hold Recovery, E-Voucher Delivery, 24-Hour Departure Reminders, and Meeting Point Pins.

---

## Explicitly OUT of V1

- Multi-day vehicle / property / equipment rental date ranges (check-in $\rightarrow$ check-out).
- Cross-agent search/discovery marketplace.
- In-app live chat (WhatsApp widget as primary direct communication).
- Multi-currency / multi-language translation engine.
- Tiered partial refund cancellation policies (single cutoff window only).
- Bring-your-own payment gateway / private merchant account.
- Enterprise plan and the V2 slices in `v2.md` (embeddable booking calendar, QR guest check-in, multiple departures per day, optional day-of guest details, ground-staff login).

---

## Technical Architecture & Quality Standards

- **Framework**: Laravel 12 on PHP 8.5.
- **UI Stack**: Livewire 4 SFCs, Tailwind CSS v4, Alpine.js, FontAwesome 6 icons.
- **Hosting**: AWS Lightsail is the origin. Cloudflare orange-clouds the platform apex (`booking.emvi` / `www`) only. `*.booking.emvi` is DNS-only to Lightsail. Ports 80/443 stay open. Trust `X-Forwarded-*` on the proxied apex. Not Laravel Cloud. Not Cloudflare for SaaS in V1.
- **Testing**: Pest 5 with **336 automated feature and unit tests (100% passing)**.
- **Code Style**: Formatted and enforced with Laravel Pint.
- **Primary Keys**: ULIDs throughout.
