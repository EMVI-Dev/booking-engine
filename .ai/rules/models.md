---
paths:
  - app/Models/PlatformSetting.php
  - app/Models/Plan.php
  - app/Models/Operator.php
---

# Models

## Operator login stays open on slug hosts
Sign-up can stay closed without locking operator login. Operators sign in on their slug or custom domain `/login`. The demo desk uses `demo.{platform}/login`. Admins never share that form.

## Plan feature labels match the desk
Plan::featureCatalog() is the single source for marketing/compare feature labels. Keep those names aligned with the operator desk menu (Guest CRM, Coupons, Calendar, Create Booking Link). Do not invent a second wording on welcome or billing compare.

## Custom reservation code prefix
Operators may set settings.reservation_code_prefix (Brand settings). Default and fallback is RSV. Codes are PREFIX-XXXXXXXX. Normalize with Reservation::normalizeCodePrefix.

## Checklist trip step is an activity
salesReadinessChecklist trip step is done when the operator has at least one activity (product). Link to products.create. Do not require a package; inventories live on activities.
