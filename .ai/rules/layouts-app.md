---
paths:
  - resources/views/layouts/app/sidebar.blade.php
---

# Layouts App

## Team is not under Storefront; Activities before Packages
Team is its own sidebar item (Business & Settings), not a Storefront tab. Catalog nav lists Activities before Packages because activities are created first, then bundled.

## Billing includes payout bank
Billing sidebar item covers plan, invoices, and payout bank (payments.edit). Storefront tabs are Brand, Policies, and Reviews. Reviews is not its own sidebar item.

## No in-app reviews nav
Do not add a Reviews sidebar item, command-palette link, or /reviews desk page. In-app guest ratings were removed. Post-trip mail uses the Reviews tab: the connected listing on Agency, otherwise the Review Platform URL.

## Phone chrome uses safe-area insets
Operator mobile chrome uses viewport-fit=cover and env(safe-area-inset-*) on the sticky header, bottom nav, and main padding. Hide the bottom nav while the menu drawer is open.

## Tablet uses the phone menu bar
Phone and tablet share the sticky header, bottom nav, and Menu drawer. The desktop sidebar and desktop header start at xl (1280px), not lg. Keep main padding for the bottom bar until xl so tablet content is not covered.

## Tablet uses the phone menu bar
Phone and tablet share the sticky header, bottom nav, and Menu drawer. Do not hide that chrome with lg or xl. Desktop sidebar starts only at 96rem on a fine pointer (hover + pointer: fine). Touch tablets, including landscape iPad Pro, keep the bottom menu. Classes: op-touch-nav, op-desk-chrome, op-desk-pad.

## Desktop sidebar from lg on a fine pointer
Phone and tablet keep the sticky header, bottom nav, and Menu drawer. Desktop sidebar and header show from 64rem only on a fine pointer (hover + pointer: fine). Do not require 96rem, or a normal desktop window loses the sidebar. Touch tablets, including landscape iPad Pro, stay on the bottom menu.
