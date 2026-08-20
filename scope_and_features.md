# Booking Engine Platform — V1 Spec (Rev. 6)

## Vision

A generic booking and inventory engine — built simple and straightforward enough that individual providers and agencies alike can use it, unlike complex legacy software that assumes heavy enterprise operations. Each agent gets their own branded booking storefront (default subdomain, upgradeable to a custom domain). No cross-agent search/discovery — platform.com is marketing + login only; each agent's page is its own destination.

## V1 Scope (confirmed)

- **Agent Storefront**: Subdomain by default (`agent-name.platform.com`), or custom domain, listing that agent's own packages and standalone products.
- **Products & Resources**: Inventory-holding items (capacity per date, resets daily), shared/reusable across packages, optionally sellable standalone.
- **Packages**: Combinations of 1+ products with their own price, inclusions/exclusions/terms seeded from linked products and freely editable after.
- **Payment Gateway Engine (Multi-Gateway Ready)**:
  - **Platform Gateway Fallback**: Platform payment gateway credentials used for automated Split Settlement directly to the agent's structured bank account (`bank_provider`, `bank_account_name`, `bank_account_number`).
  - **BYO (Bring Your Own) Merchant Account**: Agents can connect their own payment gateway credentials (`gateway_client_id`, `gateway_secret_key`) for direct settlement.
  - **Generic UI Terminology**: User-facing copy refers to "Secure Online Payment" and "Automated Payouts & Settlement" rather than specific gateway brands.
- **30-minute booking hold** on unpaid reservations, duration configurable via platform settings
- **Booking Calendar**: Derived daily capacity from product-level inventory, never stored redundantly on packages.
- **Reviews**: Gated to completed reservations.
- **Cancellation & refund engine**: Package/product-level free-cancellation window + advance-booking rule; edits to a listing only affect future bookings, with a clear notice to the agent when editing something with active future reservations.
- **Storefront Terms & Conditions**: Agent-authored terms (`terms_and_conditions`) enforced and snapshot-frozen upon booking.
- **Custom domain support**: Automatic SSL, registrar-detection-assisted onboarding for non-technical agents.
- **Brand Settings & Customization**:
  - Logo, favicon, accent brand color, business bio.
  - **Structured Payout Settlement Account**: Bank Provider, Account Name (Beneficiary), and Account Number.
  - **Storefront WhatsApp Floating Chat**: Bottom-right floating button with configurable pre-filled guest inquiry message and toggle.
  - **Dual Notification Channels**: Distinct emails for guest booking alerts (`booking_notification_email`) vs. platform billing/settlement statements (`billing_email`).
- **Multi-user agent accounts**: Owner (default, from registration) + invitable admin/reservation/finance roles.
- **Platform-level settings**: Commission rate, booking hold minutes, payment gateway sandbox/live mode, etc.
- **Onboarding Setup Checklist**: Dashboard guides new agents through Brand Settings, Terms & Conditions, Payout setup, and Products/Packages catalog creation.
- **Profile & Terms Prerequisite Guard**: Agents are required to complete their business profile (bio, WhatsApp contact, payout account reference) and storefront terms & conditions before being allowed to create inventory products or publish packages.
- **Storefront Terms & Protection Checkbox**: Mandatory acceptance on registration acknowledging platform terms and Indonesian personal data protection law (UU PDP).
- **System Theme Sync**: Automatic detection and real-time synchronization with client OS dark/light mode preferences.
- ULID primary keys throughout.

Explicitly OUT of V1:

- Cross-agent search/discovery, semantic or keyword
- In-app messaging/chat (WhatsApp link as fallback contact)
- Multi-language / multi-currency
- Escrow / custom holdbacks (Split Settlement chosen instead)
- Tiered refund percentages (single cutoff only)
- Granular permission enforcement per role (roles exist structurally in V1; owner can already do everything, so enforcement can be layered on later without a schema change)
- Hard minimum product-count rule on packages (not enforced — real usage naturally produces 2+ since standalone selling covers the 1-product case)

---

## Technical Architecture & Conventions

- **Framework**: Laravel 12 on PHP 8.5 with Pest 5 testing.
- **UI Stack**: Livewire 4 Single-File Components (SFC), Tailwind CSS v4, Alpine.js, FontAwesome 6 icons.
- **Database Testing Safety Guard**: Tests run strictly in `:memory:` SQLite; hard runtime assertion prevents accidental production database truncations.
- **Consistent UI Sizing**: Standardized 40px (`h-10`) buttons and inputs, sticky navigation sidebars, and clean card containers.
