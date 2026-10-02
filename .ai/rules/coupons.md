---
paths:
  - 'resources/views/pages/coupons/**'
---

# Coupons

## PlatformCoupon scope isolation (guest vs subscription) and dedicated reports
PlatformCoupon has two scopes: 'subscription' (for operator billing discounts) and 'guest' (for operator storefront promo codes). Operator catalog queries must always use PlatformCoupon::forGuest()->where('operator_id', ...) to prevent platform subscription billing coupons from leaking into the operator promo code list. Coupon redemptions report has a dedicated page at route('coupons.report', $coupon) with KPIs, search, filtering, and CSV export.
