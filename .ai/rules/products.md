---
paths:
  - 'resources/views/pages/products/**'
---

# Products

## Activity create and edit use x-back-link
Activity create/edit headers use x-back-link back to products.index. Do not restore the tiny muted breadcrumb arrow.

## Activity CRUD is phone-ready
Activity create and edit are phone-ready. Do not wrap them in x-desktop-only-notice or hidden lg:block. Header Delete/Cancel/Save hide below sm; the bottom action bar stays.

## Phone media dropzones are full width
On phones, cover photo is w-full aspect-video (not w-36/w-40 thumbs). Upload Cover and Add Photos buttons are w-full sm:w-auto. Gallery is 1 col on phones. Do not restore tiny cover dropzones as the phone layout.
