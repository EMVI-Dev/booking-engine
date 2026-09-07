---
paths:
  - 'resources/views/pages/auth/**'
---

# Auth

## Operator login has no admin fill
Operator `/login` must never offer Platform Admin or admin@ emails. Demo fill is only when the request host is the demo operator. Form posts to `url('/login')` so slug hosts stay on that host.
