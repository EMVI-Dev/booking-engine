---
paths:
  - app/Providers/FortifyServiceProvider.php
---

# Providers

## Operator login is shop-scoped and never admin
Operator Fortify login rejects `isAdmin()` users (admins use `/admin/login`). When `current_operator` is bound, the user must belong to that operator. Platform-host `/login` remains a fallback for any non-admin operator user.

## Register lands on slug desk
After registration, RegisterResponse sends the operator to {slug}.{platform}/auth/registration-handoff (signed, relative) so they land on their slug desk while staying logged in. Do not leave new operators on the platform apex /dashboard.
