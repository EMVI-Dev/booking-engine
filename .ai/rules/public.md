---
paths:
  - 'public/**'
---

# Public

## No static public robots.txt
Never add a static public/robots.txt. The web server (Herd locally, Laravel Cloud in production) serves files in public/ before Laravel, which would replace per-host robots (demo Disallow, operator Allow). Keep robots.txt as the StorefrontController route only.
