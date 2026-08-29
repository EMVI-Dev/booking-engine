# Booking Engine Platform — V1 Spec (Rev. 17)

## Vision & Audience

A focused, single-day capacity booking and inventory engine built specifically for **Freelance Guides, Activity Hosts, and Travel Agencies**.

Unlike complex legacy software that assumes enterprise hotel or multi-day vehicle rental operations, this platform is tailored to **Daily Capacity Booking**:
- Each operator gets their own branded booking storefront (default subdomain `slug.booking.emvi`, upgradeable to custom domain `yourbrand.com`).
- No cross-agent marketplace search/discovery — the root platform domain handles marketing and authentication; each operator's storefront is an independent booking destination.
- Single calendar date selection (`requested_date`) and daily capacity limits per activity (`capacity_per_day`).

---

## Core Domain Entities

| Entity | DB Table | Concept & Purpose |
| :--- | :--- | :--- |
| **Tour Packages** | `packages` | Full curated day tours, excursions, and multi-activity packages with chronological itineraries, inclusions, and exclusions. Bundles 1+ single activities. |
| **Single Activities** | `products` | Standalone bookable activities, workshop sessions, day passes, guide hire, or transport services. Can be sold standalone or linked into packages. |
| **Reservations** | `reservations` | Single-date booking records (`#RSV-XXXX`) tracking guest info, selected date, pax count, payment status, and snapshot-frozen terms. |

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
    - **Capacity Density Heatmap**: Monthly color-coded density calendar showing sold-out, high-occupancy, and open days.
    - **Print-Perfect Daily Manifest**: High-contrast A4 printable run-sheet with check-in pen tick-boxes (`[ ] Check`), passenger details, notes, and a 3-part ground crew sign-off block (Tour Guide, Driver/Vehicle ID, Dispatch Officer).
- **Streamlined Navigation & Sidebar**:
    - Clean hierarchy: *Dashboard, Reservations, Calendar, Tour Packages, Single Activities, Coupons & Discounts, Storefront Settings, Subscription & Billing*.
- **Native Mobile Navigation Bar & Mobile Card Lists (`md:hidden`)**:
    - Sticky glassmorphism mobile bottom navigation bar (`lg:hidden fixed bottom-0 left-0 right-0 z-40 h-16`) for 1-thumb operations.
    - All data tables convert into responsive mobile cards on smartphone screens (`< 768px`).

### 3. Branded Storefront & Guest Experience
- **Subdomain & Custom Domain Routing**:
    - Default: `agent-slug.platform.com`.
    - Custom Domain: `yourbrand.com` with automated SSL certificate provisioning and live DNS inspector.
- **Segmented Storefront Navigation**:
    - Direct access to *Catalog (Home)*, *Tour Packages*, *Single Activities*, and *Terms & Policies*.
- **Direct Checkout & 30-Minute Hold Recovery**:
    - Date picker with real-time capacity validation and instant 30-minute hold countdown.
    - Payment resumption endpoint (`/reservations/{reservation}/pay`) if a guest closes their checkout tab.
- **Tier-Gated AI Search Discovery & ChatGPT Recommendation Engine (`/llms.txt`)**:
    - Exclusive flagship feature of the top **AI Ultimate Agency** tier.
    - Automatically serves structured `/llms.txt` and `/llms-full.txt` Markdown catalog feeds for ChatGPT, Perplexity, Claude, and Gemini crawlers.
- **Google Search Console (GSC) Domain Ownership**:
    - One-click Google site verification tag injection in storefront `<head>`.
- **WhatsApp Floating Widget**:
    - Bottom-right floating chat with customizable pre-filled inquiry messages.

### 4. Commercial & Subscription Model (4 Tiers)
- **Starter Essential (Free / Base Tier)**:
    - **100% Net Payout to Operator**. Standard 5.0% Guest Service Fee added at checkout. Up to 5 package listings, custom subdomain, and WhatsApp floating widget.
- **Pro Operator (Rp 299.000 / mo | Rp 2.990.000 / yr)**:
    - **100% Net Payout to Operator**. Up to 25 packages, unlocking **Google Calendar 1-Click & Live iCal Feed Sync**, **Guest Directory CRM & Lifetime Spend Analytics**, **Meta Pixel & GA4 ROAS tracking**, and **1-Click WhatsApp Dispatch Center**.
- **Agency Ultimate (Rp 699.000 / mo | Rp 6.990.000 / yr)**:
    - **100% Net Payout to Operator**. Unlimited packages, unlocking **Custom Domain (`yourbrand.com`) with automated SSL**, **BYO Custom Payment Gateway Keys (0% Guest Fee direct settlement)**, and **Monthly Capacity Heatmap Analytics**.
- **AI Ultimate Agency (Rp 999.000 / mo | Rp 9.990.000 / yr)**:
    - **100% Net Payout to Operator**. Everything in Agency Ultimate plus **Tier-Gated AI Search Discovery (`/llms.txt`)** and unthrottled AI search crawler access.
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
- **BYO (Bring Your Own) Merchant Account**:
    - Agency Ultimate & AI Ultimate Agency operators can connect their own DOKU credentials for direct settlement.

### 6. Notifications & Communication
- **2-Step Guest Email Lifecycle**:
    - Step 1 (`GuestBookingCreatedMail`): Hold confirmation with countdown and payment link.
    - Step 2 (`GuestBookingConfirmedMail`): Verified payment receipt with digital e-ticket voucher.
- **Agent Notification (`AgentNewBookingNotificationMail`)**:
    - Instant email alert on paid booking capture.
- **1-Click WhatsApp Dispatch Center**:
    - Pre-formatted messages for Payment Hold Recovery, E-Voucher Delivery, 24-Hour Departure Reminders, and Meeting Point Pins.

---

## Explicitly OUT of V1

- Multi-day vehicle / property / equipment rental date ranges (check-in $\rightarrow$ check-out).
- Cross-agent search/discovery marketplace.
- In-app live chat (WhatsApp widget as primary direct communication).
- Multi-currency / multi-language translation engine.
- Tiered partial refund cancellation policies (single cutoff window only).

---

## Technical Architecture & Quality Standards

- **Framework**: Laravel 12 on PHP 8.5.
- **UI Stack**: Livewire 4 SFCs, Tailwind CSS v4, Alpine.js, FontAwesome 6 icons.
- **Testing**: Pest 5 with **198 automated feature and unit tests (100% passing)**.
- **Code Style**: Formatted and enforced with Laravel Pint.
- **Primary Keys**: ULIDs throughout.
