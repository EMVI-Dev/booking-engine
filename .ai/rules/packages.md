---
paths:
  - 'resources/views/pages/packages/**'
---

# Packages

## Package create and edit use x-back-link
Package create/edit headers use x-back-link back to packages.index. Do not restore the tiny muted breadcrumb arrow.

## Package CRUD is phone-ready
Package create and edit are phone-ready. Do not wrap them in x-desktop-only-notice or hidden lg:block. The bundle qty stepper must stay at least h-9.

## Bundle picker is search-first
Do not restore a full checkbox wall of activities on package create/edit. Selected items stay listed with qty. If more than 8 remain unselected, require search. x-package-bundle-picker and ManagesPackageProductBundle own this.

## Phone media dropzones are full width
On phones, cover photo is w-full aspect-video (not w-40 thumbs). Upload Cover and Add Photos buttons are w-full sm:w-auto. Gallery is 1 col on phones.

## Soft warning when package is not cheaper than activities
Soft amber warning on package create/edit when package price >= sum of linked activity prices × qty (bundledSeparateTotal). Never block save or publish. Livewire: ManagesPackageProductBundle owns the math; wire:model.live on price and bundle qty.

## Always show package vs activities price hint
When activities are linked, always show a price comparison hint (deal or amber warning). Auto-populate inclusions dispatches a success toast. Hint appears under price and under the bundle picker.

## Suggest 15% package price in 10–20% band
Smart package price: range Low=floor_10k(separate×0.80) to High=floor_10k(separate×0.90); suggested Mid=floor_10k(separate×0.85). Use suggested price button sets price and toasts. Soft only; never force.

## One price hint card, no percent labels
Bundle price hint is one combined card: separate vs package, recommended range, suggested price, Apply. Do not show discount percentages in the UI (math stays 10/15/20% under the hood). Amber when package >= separate; indigo otherwise.

## Dismiss suggest; import activity gallery
No Auto-populate Inclusions on package create/edit. After Use suggested price, dismiss the smart price card (reappears if bundle changes or price warning). Use activity photos copies up to 2 gallery images per selected activity into the package gallery.

## Package form: activities before price
Package create/edit card order is Basics → Activities → Price → Photos → Story & policies. Smart price hint appears once, under the price field only.

## Package form responsive density
Package create/edit: denser phone padding (p-4, rounded-2xl), form max-w-5xl, price field beside smart hint from lg, full-width h-11 save/apply/delete hits on phone. Bundle catalog is 2-col from sm.

## Smart price hint once only
Smart price hint appears once beside/under the price field only (never under the bundle picker). No Auto-populate Inclusions toast. Prefer this over any older dual-hint wording.

## Package basics grid balance
Package basics grid: Title full, Category full with Popular chips, then Location | Status side by side. Do not put Popular chips in a half-column next to Location. Form is full width (no max-w-5xl) so header actions align with cards.

## Activity media import dedupe + cover modal
Use activity photos skips sources already imported (no duplicate copies). Cover has Use activity cover opening a modal of selected activity covers; applyActivityCover copies into packages/covers.

## Package form platform colors
Package create/edit chrome uses platform op-* tokens and brand yellow. No indigo/sky/emerald section accents. Amber stays for price warnings; rose for delete/danger only.

## Package price card UX
Package price card: short Price per guest label, live Rp preview, empty-state nudge when no activities, and a scannable Separate/Package/Guest saves strip plus recommended range + Use suggested. Formula unchanged.

## Package includes use $this->price
In package Blade @include partials (and @php blocks), read Livewire state via $this->price — bare $price can throw PropertyNotFoundException.
