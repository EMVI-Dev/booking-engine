---
paths:
  - resources/css/app.css
---

# Css

## Keep .op-input padding overridable for prefix icons
Keep `.op-input` in `@layer components` (not unlayered). Unlayered `padding-inline` beats Tailwind `pl-*`/`ps-*`, so prefix icons sit on the first letters. Prefix maps on `.op-input.pl-8`–`pl-12` (and `ps-*`) must stay so search, hex color, and social fields keep start padding. Do not drop those classes from icon-prefixed inputs.
