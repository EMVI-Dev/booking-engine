---
paths:
  - resources/css/app.css
---

# Css

## Keep .op-input padding overridable for prefix icons
Keep `.op-input` in `@layer components` (not unlayered). Unlayered `padding-inline` beats Tailwind `pl-*`/`ps-*`, so prefix icons sit on the first letters. Prefix maps on `.op-input.pl-8`–`pl-12` (and `ps-*`) must stay so search, hex color, and social fields keep start padding. Do not drop those classes from icon-prefixed inputs.

## Phone inputs are 16px
.op-input is 1rem on phones so iOS does not zoom on focus, and 0.8125rem from sm up. Keep it in @layer components and keep prefix pl/ps padding maps.

## Light mode: yellow is fill, not text
In light mode, never use --op-brand yellow as text on white chrome. Mobile active tab uses ink + yellow marker. Platform Admin in light dropdowns uses ink, not yellow text. Desk and admin keep platform yellow; only the shop uses operator brand_color.
