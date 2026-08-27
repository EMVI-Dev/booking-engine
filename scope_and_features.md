# Booking Engine Platform — V1 Spec (Rev. 16)

## Vision

A generic booking and inventory engine — built simple and straightforward enough that individual providers and agencies alike can use it, unlike complex legacy software that assumes heavy enterprise operations. Each agent gets their own branded booking storefront (default subdomain, upgradeable to a custom domain). No cross-agent search/discovery — platform.com is marketing + login only; each agent's page is its own destination.

## V1 Scope (confirmed)

- **1-Click Direct Booking & Payment Link Generator (Operator Portal)**:
    - Operators can manually create a reservation on behalf of guests from any inquiry channel (WhatsApp, phone, Instagram DM, email, or walk-in).
    - Generates a 30-minute hold reservation (`#RSV-XXXX`), auto-creates/indexes the guest in CRM, and provides a 1-click **"Copy Payment Link"** to share anywhere, plus a 1-click **"Send via WhatsApp"** quick action.
- **Tier-Gated AI Discovery Engine & ChatGPT / LLM Search Recommendation (`/llms.txt`)**:
    - Exclusive flagship feature of the top **AI Ultimate Agency** tier (`Rp 999.000 / mo | Rp 9.990.000 / yr`).
    - Automatically generates dynamic `/llms.txt` and `/llms-full.txt` Markdown catalog feeds serving structured trip details, inclusions, terms, and direct booking links for ChatGPT, Perplexity, Claude, and Gemini.
    - Dynamic `/robots.txt` configuration unblocks AI search engine crawlers (`GPTBot`, `OAI-SearchBot`, `PerplexityBot`, `ClaudeBot`) for AI Ultimate operators, while gating access on lower plans.
- **Google Search Console (GSC) Domain Ownership Verification**:
    - Operators can configure their **Google Site Verification Tag** in Storefront Settings.
    - Automatically injected as a `<meta name="google-site-verification" content="...">` tag in the storefront `<head>` for 1-click Google Search Console domain ownership verification without requiring OAuth logins.
- **Native Mobile Navigation Bar & Mobile-First UX (Operator Portal)**:
    - Sticky glassmorphism mobile bottom navigation bar (`lg:hidden fixed bottom-0 left-0 right-0 z-40 h-16`) providing 1-thumb access to _Dashboard_, _Bookings (with real-time pending badge count)_, _Center Raised Action (`+ New Link`)_, _Calendar_, and _Menu Drawer_.
- **Platform-Wide Responsive Mobile Card Lists (`md:hidden`)**:
    - All horizontal HTML data tables across the platform (Reservations, Dashboard Recent Activity, Billing Invoices, Wallet Ledger & Payouts, Guest CRM Directory, Platform Admin Operators, Platform Admin Payouts & Transaction Feeds) cleanly convert into simple responsive card lists (`md:hidden`) with Livewire pagination on smartphone screens (`< 768px`).
- **Subscription Plans & Commercial Model (4 Commercial Tiers)**:
    - **Starter Essential (Free / Base Tier)**: **100% Net Payout to Operator**. Standard 5.0% Guest Service Fee added at checkout. Up to 5 package listings, custom subdomain `slug.booking.emvi`, DOKU checkout, and WhatsApp floating widget.
    - **Pro Operator (Rp 299.000 / mo | Rp 2.990.000 / yr)**: **100% Net Payout to Operator**. Up to 25 packages, unlocking **Google Calendar 1-Click & Live iCal Feed Sync**, **Guest Directory CRM & Lifetime Spend Analytics**, **Meta Pixel & GA4 ROAS tracking**, and **1-Click WhatsApp Dispatch Center**.
    - **Agency Ultimate (Rp 699.000 / mo | Rp 6.990.000 / yr)**: **100% Net Payout to Operator**. Unlimited packages, unlocking **Custom Domain (`yourbrand.com`) with automated SSL**, **BYO Custom Payment Gateway Keys (0% Guest Fee direct settlement)**, and **Monthly Capacity Heatmap Analytics**.
    - **AI Ultimate Agency (Rp 999.000 / mo | Rp 9.990.000 / yr)**: **100% Net Payout to Operator**. Everything in Agency Ultimate plus **Tier-Gated AI Search Discovery & ChatGPT Recommendation Engine (`/llms.txt`)** and unthrottled AI crawler access (`GPTBot`, `PerplexityBot`, `ClaudeBot`).
    - **Agent Subscription & Billing Portal (`/settings/plan`)**: Interactive tier switcher with monthly and annual billing options, ultra-responsive badge containers (`flex-wrap`), 2-column metrics grid, and touch-scrolling navigation tab bar (`no-scrollbar whitespace-nowrap`).
    - **Platform Master Plans Manager (`/admin/plans`)**: Master tool to configure plan pricing, commission take rates, package limits, and individual feature toggles.
    - **Platform Master Global Settings (`/admin/platform`)**: Configurable global Guest Service Fee (%) and unpaid booking hold timeout.
- **Platform Master Custom Domain & DNS Verification Center (`/admin/domains`)**:
    - Central directory of all operator subdomains and external custom domains.
    - **Live DNS Inspector**: Built-in DNS resolver test (`dns_get_record`) verifying CNAME and A record routing against the platform domain.
    - **1-Click Activation & SSL**: Instant domain approval and SSL certificate issuance status management.
- **Agent Storefront**: Subdomain by default (`agent-name.platform.com`), or custom domain, listing that agent's own packages and standalone products.
- **Products & Resources**: Inventory-holding items (capacity per date, resets daily), shared/reusable across packages, optionally sellable standalone.
- **Packages**: Combinations of 1+ products with their own price, inclusions/exclusions/terms seeded from linked products and freely editable after.
- **Payment Gateway Engine (DOKU Jokul & Multi-Gateway Ready)**:
    - **Live DOKU Jokul Hosted Checkout**: Full integration with `POST /checkout/v1/payment` using HMAC-SHA256 signature authorization, redirecting guests to DOKU's official hosted payment page for Virtual Accounts (BCA, Mandiri, BRI, BNI), QRIS, Credit Cards (3D Secure OTP), and E-Wallets.
    - **Zero-Config Offline Simulation**: Built-in payment simulator (`/checkout/simulate`) for instant local testing and offline demos without requiring live credentials.
    - **Live Status Inquiry & Auto-Sync**: Real-time payment verification (`GET /orders/v1/status/{invoice}`) automatically triggered upon guest return to the receipt page and via a 1-click **"Sync with DOKU"** button in the agent portal.
    - **100% Net Operator Payout (0.0% Commission Model)**: Operators receive 100% of listed package rates. Platform revenue is generated via transparent Guest Service Fees (5.0%, capped at Rp 250.000) and SaaS subscription plans.
    - **Automated DOKU BI-FAST Payout Engine**: Payout requests up to Rp 10.000.000 are automatically disbursed via DOKU's Fund Transfer API (`POST /disbursement/v1/transfer`) into the operator's bank account (BCA, Mandiri, BRI, BNI) in under 3 seconds.
    - **BYO (Bring Your Own) Merchant Account**: Agency Ultimate & AI Ultimate Agency operators can connect their own DOKU payment gateway credentials for 100% direct settlement into their merchant account.
- **30-Minute Booking Hold & Payment Recovery**:
    - 30-minute booking hold on unpaid reservations (duration configurable via platform settings).
    - Direct payment resumption endpoint (`/reservations/{reservation}/pay`) allowing guests to complete checkout or retry payment if their initial browser session was closed.
- **2-Step Guest Email Lifecycle & Notifications**:
    - **Step 1 (`GuestBookingCreatedMail`)**: Dispatched immediately upon booking creation with reference `#RSV-XXXX`, hold expiration countdown, and a prominent **`[💳 Complete Payment (Pay Now)]`** link.
    - **Step 2 (`GuestBookingConfirmedMail`)**: Dispatched upon verified payment capture with digital e-ticket voucher, payment receipt breakdown, and meeting point check-in instructions.
    - **Agent Notification (`AgentNewBookingNotificationMail`)**: Real-time email alert to the operator when a paid booking is received.
- **1-Click WhatsApp Dispatch Center**:
    - Pre-formatted messages with international phone normalization (`628...`):
        - **Payment Hold Recovery Link**: Direct link to `/reservations/{id}/pay` with remaining hold time.
        - **E-Voucher & Ticket**: 1-click digital voucher link.
        - **24-Hour Departure & Packing Reminder**: Packing tips, meeting point, and voucher link.
        - **Meeting Point & Departure Pin**: Instructions and check-in/uniform guidance.
- **Google Calendar & iCal Feed Subscriptions**:
    - **1-Click Add to Google Calendar**: Instant calendar links generated for both guest receipts and agent reservation drawers.
    - **Live iCal Feed (`/calendar/feed/{token}`)**: Tokenized iCal feed allowing operators to subscribe their Google Calendar, Apple Calendar, or Outlook to all incoming confirmed trips.
- **Guest Management & CRM**:
    - Dedicated guest directory tracking lead guests, total trips, lifetime spend, booking histories, and direct contact actions.
- **Interactive Design System & Date Picker**:
    - Custom `<x-date-picker>` component aligned with system design tokens (calendar grid navigation, advance booking cutoff rules, quick selection presets: _Today, Tomorrow, +2 Days_).
    - Standardized `<x-select>` and UI form components across all agent portal views.
- **Booking Terms & Cancellation Engine**:
    - Mandatory agreement checkbox gating disabling booking submission until terms are explicitly accepted.
    - Package/product-level free-cancellation window + advance-booking cutoff rules; snapshot-frozen terms recorded at time of booking.
- **Agent Wallet & Payout Ledger**:
    - Real-time wallet tracking (available balance, pending escrow, payout requests, status filters, and exportable ledger).
- **Reservations Chronological Sorting**:
    - Incoming trips sorted nearest-departure first (`requested_date ASC`) so operators see immediate upcoming departures at the top.
- **Custom Domain Support**: Automatic SSL, registrar-detection-assisted onboarding for non-technical agents.
- **Brand Settings & Customization**:
    - Logo, favicon, accent brand color, business bio.
    - **Structured Payout Settlement Account**: Bank Provider, Account Name (Beneficiary), and Account Number.
    - **Storefront WhatsApp Floating Chat**: Bottom-right floating button with configurable pre-filled guest inquiry message, operating schedule, and online status detection.
    - **Dual Notification Channels**: Distinct emails for guest booking alerts (`booking_notification_email`) vs. platform billing/settlement statements (`billing_email`).
- **Multi-User Agent Accounts**: Owner (default, from registration) + invitable admin/reservation/finance roles.
- **Platform-Level Settings**: Commission rate, booking hold minutes, payment gateway sandbox/live mode, etc.
- **Onboarding Setup Checklist**: Dashboard guides new agents through Brand Settings, Terms & Conditions, Payout setup, and Products/Packages catalog creation.
- **Profile & Terms Prerequisite Guard**: Agents are required to complete their business profile and storefront terms & conditions before creating inventory products or publishing packages.
- **System Theme Sync**: Automatic detection and real-time synchronization with client OS dark/light mode preferences.
- ULID primary keys throughout.

---

### Explicitly OUT of V1:

- Cross-agent search/discovery, semantic or keyword
- In-app messaging/chat (WhatsApp link as fallback contact)
- Multi-language / multi-currency
- Tiered refund percentages (single cutoff only)

---

## Technical Architecture & Conventions

- **Framework**: Laravel 12 on PHP 8.5 with Pest 5 testing (169 automated test suites).
- **UI Stack**: Livewire 4 Single-File Components (SFC), Tailwind CSS v4, Alpine.js, FontAwesome 6 icons.
- **Database Testing Safety Guard**: Tests run strictly in `:memory:` SQLite; hard runtime assertion prevents accidental production database truncations.
- **Consistent UI Sizing**: Standardized 40px (`h-10`) buttons and inputs, sticky navigation sidebars, and clean card containers.
