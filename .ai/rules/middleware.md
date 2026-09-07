---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## Demo storefront is never indexed
The demo operator (is_demo, slug demo.{platform-domain}) must never be crawled or indexed: robots Disallow:/, empty sitemap, 404 llms files, HTML noindex, and X-Robots-Tag. Do not reuse unpublished-storefront robots (it still advertises a sitemap). Do not noindex the platform or admin just because the demo operator exists in the database. Welcome links to the demo must be rel=nofollow.
