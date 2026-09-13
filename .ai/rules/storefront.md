---
paths:
  - 'resources/views/storefront/**'
---

# Storefront

## Demo desk login stays on the shop host
Only the demo shop may advertise the operator desk. Those links must stay on the current host (`url('/login')` → `{slug}/login`), never the platform `/login`. Real operator storefronts must not show Staff, Operator desk, or login.

## Storefront has no in-app reviews
Do not render an in-app verified-reviews block on the shop. Guests review on the operator Review Platform URL from the post-trip email.

## Storefront has no external website link
Do not render a Website globe from social_links. Guests are already on the operator shop. Instagram, Facebook, and WhatsApp are the public profile links.

## Google reviews slider is allowed
The shop may show a Google-attributed review slider from the operator's connected public listing (settings.google_place). Hide it when disconnected or stale. Do not restore an in-app verified-reviews block or a guest review form. Post-trip mail uses the listing when Agency has one connected, otherwise the Review Platform URL from the Reviews tab.

## Storefront accents follow operator brand
Storefront brand-theme maps the full brand-* scale from the operator Brand Accent Color. Fills use brand-600 + brand-foreground. Light text accents use brand-700/800 (darkened mixes), not raw yellow on white. Desk chrome stays platform yellow and does not read operator brand_color.

## Hide empty catalog chrome on guest shop
Guest chrome hides Tour Packages (home section, navbar, footer) when there are no published packages, and hides Single Activities links when there are no published standalone products. Empty /tours (storefront.packages) redirects to home. Do not hide Packages on the operator desk.

## Listing cards say View details, not Book
Catalog cards (home, packages index, products index) use View details and link to the trip page. Book Now stays on package/product detail pages (navbar, sticky mobile bar, booking box). Guests should open details before booking.

## Dark brand text stays lightened
In dark mode, brand-400/700/800 stay lightened mixes of the Brand Accent. Never set dark brand-400 to the raw brand color — dark accents (e.g. #0f172a) become invisible on zinc surfaces (active Catalog pill). Active desktop nav uses text-brand-800 in both modes.

## Do not show daily capacity to guests
capacity_per_day is operator inventory used for booking enforcement. Do not show N/day badges on catalog cards or “daily capacity verified” copy on product detail. Guests only need availability through the booking flow.

## Product detail shows inclusions and exclusions
Product detail mirrors package detail: show both inclusions and exclusions in a two-column What is Included / Not Included block when either list is present. Do not ship inclusions-only chips on the product page.

## Detail pages prefer listing policy copy
Product and package detail pages prefer listing cancellation_terms, then listing terms_and_conditions, then operator shop terms. Product detail also shows category + location chips, a free-cancellation meta tile (not capacity), and an Activity Overview section.

## Detail pages: meta, gallery, share, breadcrumb
Detail pages show book-ahead tiles when advance_booking_hours > 0, breadcrumbs (Home › Tour Packages|Single Activities › title), Copy link + Ask about this trip (WhatsApp) via listing-share, and Alpine lightbox via listing-gallery. Package detail lists bundled activities from getRequiredProducts (link when published + standalone). Package location uses location, not destination.
