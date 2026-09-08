---
paths:
  - resources/views/components/settings-nav.blade.php
  - resources/views/components/billing-nav.blade.php
  - resources/views/components/back-link.blade.php
  - resources/views/components/date-picker.blade.php
---

# Components

## Team is not a Storefront tab
Team lives on its own page (settings.team) with a sidebar item, not in this nav.

## Storefront tabs are Brand, Policies, and Reviews
Storefront tabs are Brand, Policies, and Reviews. Reviews is its own tab, not a Brand field and not a sidebar item. Payout bank lives under Billing (billing-nav), not here.

## Payout bank is a Billing tab
Billing tabs are Plan, Invoices, and Payout bank account. Payout bank is money, not storefront.

## Operator back links are a large ink-colored control
Operator create/edit pages use x-back-link (op-back-link): h-9, text-sm, text-op-ink, hover:bg-op-muted. Do not use tiny muted breadcrumbs (text-xs, text-slate-500, 10px arrows) as the only way back.

## Date picker popover teleports to body
Date picker calendar teleports to body with fixed position so overflow-hidden modal shells and sticky footers cannot clip it. Modal click-outside must ignore [data-date-picker-popover].

## Storefront tabs include Reviews
Storefront tabs are Brand, Policies, and Reviews. Reviews is review-settings.edit. Team stays its own sidebar page. Payout bank stays under Billing.
