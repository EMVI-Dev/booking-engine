# Booking Engine Platform — V1 Spec (Rev. 10)

## Vision

A generic booking and inventory engine — built simple and straightforward enough that individual providers and agencies alike can use it, unlike complex legacy software that assumes heavy enterprise operations. Each agent gets their own branded booking storefront (default subdomain, upgradeable to a custom domain). No cross-agent search/discovery — platform.com is marketing + login only; each agent's page is its own destination.

## V1 Scope (confirmed)

- **1-Click Direct Booking & Payment Link Generator (Operator Portal)**:
  - Operators can manually create a reservation on behalf of guests from any inquiry channel (WhatsApp, phone, Instagram DM, email, or walk-in).
  - Generates a 30-minute hold reservation (`#RSV-XXXX`), auto-creates/indexes the guest in CRM, and provides a 1-click **"Copy Payment Link"** to share anywhere, plus a 1-click **"Send via WhatsApp"** quick action.
- **Custom Marketing Analytics & Tracking Pixels (Storefront Injection)**:
  - Operators can configure **Google Analytics 4 (`G-XXXXXXXXXX`)**, **Meta / Facebook Pixel (`1234567890`)**, and **Google Tag Manager (`GTM-XXXXXXX`)** in Storefront Settings.
  - Automatically injected across all storefront pages, with **Meta `Purchase` & GA4 `purchase` conversion events** triggered on booking confirmation receipts for full ROAS advertising measurement.
- **12-Hour Automated Post-Trip Review Request Email**:
  - Operators can set their **Google Maps / TripAdvisor Review URL** in Storefront Settings.
  - An automated scheduled command (`trips:send-review-requests`) runs hourly, detects trips completed 12 hours ago, and sends a branded `GuestReviewRequestMail` with a 5-star review invitation button.
- **Subscription Plans & Commercial Model (100% Net to Operator + Guest Service Fee)**:
  - **Starter Essential (Free / Base Tier)**: **100% Net Payout to Operator (0% operator commission deducted)**. Standard 5.0% Guest Service Fee added at checkout. Unlimited team staff members, up to 5 package listings, custom subdomain `slug.booking.emvi`, standard DOKU checkout, and WhatsApp floating widget.
  - **Growth Pro (Rp 199.000 / mo | Rp 1.990.000 / yr)**: **100% Net Payout to Operator**. Up to 25 packages, **Unlimited team staff members**, unlocking **Google Calendar 1-Click & Live iCal Feed Sync**, **Guest Directory CRM & Lifetime Spend Analytics**, and **1-Click WhatsApp Dispatch Center**.
  - **Enterprise Ultimate (Rp 599.000 / mo | Rp 5.990.000 / yr)**: **100% Net Payout to Operator**. Unlimited packages and **Unlimited team staff members**, unlocking **Custom Domain (`yourbrand.com`) with automated SSL** and **BYO Custom Payment Gateway Keys (0% Guest Fee direct settlement)**.
  - **Agent Subscription & Billing Portal (`/settings/plan`)**: Interactive tier switcher with monthly and annual billing options and transparent fee breakdowns.
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
  - **Tier-Based Split Settlement**: Commission split calculated dynamically based on the operator's active subscription tier (10% on Starter, 0% on Growth & Enterprise).
  - **BYO (Bring Your Own) Merchant Account**: Enterprise tier agents can connect their own payment gateway credentials for direct settlement.
- **30-Minute Booking Hold & Payment Recovery**:
  - 30-minute booking hold on unpaid reservations (duration configurable via platform settings).
  - Direct payment resumption endpoint (`/reservations/{reservation}/pay`) allowing guests to complete checkout or retry payment if their initial browser session was closed.
- **2-Step Guest Email Lifecycle & Notifications**:
  - **Step 1 (`GuestBookingCreatedMail`)**: Dispatched immediately upon booking creation with reference `#RSV-XXXX`, hold expiration countdown, and a prominent **`[💳 Complete Payment (Pay Now)]`** link.
  - **Step 2 (`GuestBookingConfirmedMail`)**: Dispatched upon verified payment capture with digital e-ticket voucher, payment receipt breakdown, and harbor check-in instructions.
  - **Agent Notification (`AgentNewBookingNotificationMail`)**: Real-time email alert to the operator when a paid booking is received.
- **1-Click WhatsApp Dispatch Center**:
  - Pre-formatted messages with international phone normalization (`628...`):
    - **Payment Hold Recovery Link**: Direct link to `/reservations/{id}/pay` with remaining hold time.
    - **E-Voucher & Ticket**: 1-click digital voucher link.
    - **24-Hour Departure & Packing Reminder**: Packing tips, meeting point, and voucher link.
    - **Harbor Check-in & Meeting Point Pin**: Instructions and booth/uniform guidance.
- **Google Calendar & iCal Feed Subscriptions**:
  - **1-Click Add to Google Calendar**: Instant calendar links generated for both guest receipts and agent reservation drawers.
  - **Live iCal Feed (`/calendar/feed/{token}`)**: Tokenized iCal feed allowing operators to subscribe their Google Calendar, Apple Calendar, or Outlook to all incoming confirmed trips.
- **Guest Management & CRM**:
  - Dedicated guest directory tracking lead guests, total trips, lifetime spend, booking histories, and direct contact actions.
- **Interactive Design System & Date Picker**:
  - Custom `<x-date-picker>` component aligned with system design tokens (calendar grid navigation, advance booking cutoff rules, quick selection presets: *Today, Tomorrow, +2 Days*).
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

- **Framework**: Laravel 12 on PHP 8.5 with Pest 5 testing (135+ automated test suites).
- **UI Stack**: Livewire 4 Single-File Components (SFC), Tailwind CSS v4, Alpine.js, FontAwesome 6 icons.
- **Database Testing Safety Guard**: Tests run strictly in `:memory:` SQLite; hard runtime assertion prevents accidental production database truncations.
- **Consistent UI Sizing**: Standardized 40px (`h-10`) buttons and inputs, sticky navigation sidebars, and clean card containers.
