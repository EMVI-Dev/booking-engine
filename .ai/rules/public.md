---
paths:
  - 'public/**'
---

# Public

## No static public robots.txt
Never add a static public/robots.txt. Herd/Caddy serves files in public/ before Laravel, which would replace per-host robots (demo Disallow, operator Allow). Keep robots.txt as the StorefrontController route only.
