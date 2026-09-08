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
