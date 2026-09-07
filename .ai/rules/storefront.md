---
paths:
  - 'resources/views/storefront/**'
---

# Storefront

## Demo desk login stays on the shop host
Only the demo shop may advertise the operator desk. Those links must stay on the current host (`url('/login')` → `{slug}/login`), never the platform `/login`. Real operator storefronts must not show Staff, Operator desk, or login.
