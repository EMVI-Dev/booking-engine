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
