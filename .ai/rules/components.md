---
paths:
  - resources/views/components/settings-nav.blade.php
  - resources/views/components/billing-nav.blade.php
  - resources/views/components/back-link.blade.php
---

# Components

## Team is not a Storefront tab
Team lives on its own page (settings.team) with a sidebar item, not in this nav.

## Storefront tabs are Brand and Policies
Storefront tabs are Brand and Policies only. Payout bank lives under Billing (billing-nav), not here.

## Payout bank is a Billing tab
Billing tabs are Plan, Invoices, and Payout bank account. Payout bank is money, not storefront.

## Operator back links are a large ink-colored control
Operator create/edit pages use x-back-link (op-back-link): h-9, text-sm, text-op-ink, hover:bg-op-muted. Do not use tiny muted breadcrumbs (text-xs, text-slate-500, 10px arrows) as the only way back.
