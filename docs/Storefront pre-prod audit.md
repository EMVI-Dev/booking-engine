# Storefront pre-prod audit

_Guest shop only · desktop + mobile · templates + StorefrontController + storefront tests · 2026-09-13_

> **Ship advice:** Flow is production-capable. Fix the five P0 items before push — especially mobile safe-area and reservation noindex. P1 can land in a fast follow if you need to ship tonight.

**Summary:** P0 ×5 (before prod) · P1 ×9 (should fix soon) · P2 ×8 (polish backlog) · 21 storefront tests green

---

## Guest flow (already wired)

Home → /tours|/services → detail → 30-min hold → DOKU → receipt / e-ticket · Find Booking recovery · pending/suspended gates

`Catalog` · `Detail + book` · `Hold 30m` · `Checkout` · `Receipt` · `Find Booking`

---

## P0 — must before prod (5 items)

| ID   | Fix                                | Why                                                                                                                             | Where                                                     |
| ---- | ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------- |
| P0-1 | Mobile sticky Book bar safe-area   | `fixed bottom-0` with no `env(safe-area-inset-bottom)`; detail main only `pb-16` — clips on iPhone home indicator               | package.blade.php, product.blade.php                      |
| P0-2 | noindex guest reservation URLs     | confirmation has no robots noindex; robots.txt does not `Disallow /reservations/` — public_token pages can be indexed if leaked | confirmation.blade.php, StorefrontController::robots      |
| P0-3 | Empty shop home state              | Zero packages + zero products still shows hero/tabs with nothing bookable                                                       | index.blade.php, StorefrontController::index              |
| P0-4 | Empty /services parity with /tours | /tours redirects home when no packages; /services stays an empty catalog page                                                   | StorefrontController::allProducts, EmptyCatalogChromeTest |
| P0-5 | Sitemap omit empty catalog URLs    | Always emits /tours and /services even when empty (soft SEO waste + redirect for tours)                                         | StorefrontController::sitemap                             |

## P1 — should fix soon (9 items)

| ID   | Item                                  | Why                                                                   |
| ---- | ------------------------------------- | --------------------------------------------------------------------- |
| P1-1 | One booking-box instance              | Desktop + mobile sheet both mount Livewire — double payload on phones |
| P1-2 | Live hold countdown                   | Receipt shows static minutes left; should tick from `hold_expires_at` |
| P1-3 | Unify per person / per unit copy      | Product sticky says unit; booking box always says per person          |
| P1-4 | Drop hardcoded 24h hero trust line    | Listings use `free_cancellation_hours`; home always says 24h          |
| P1-5 | Remove guest daily-capacity marketing | packages.blade.php still says "Guaranteed daily capacity"             |
| P1-6 | Touch targets ≥ 44px                  | Hamburger, share, sheet close are h-8/h-9                             |
| P1-7 | Booking sheet a11y                    | No `role=dialog` / `aria-modal` / focus trap / Escape                 |
| P1-8 | Limit home catalog query              | Loads all published listings then `take(5/6)` in Blade                |
| P1-9 | Stronger email helper at checkout     | Optional email → no hold mail; recovery leans on WA / Find Booking    |

---

## P2 — polish backlog

| Idea                       | Note                           |
| -------------------------- | ------------------------------ |
| Product free-cancel badges | Parity with package cards      |
| Reviews scroll affordance  | Fade / swipe hint              |
| Calendar real times        | Not 08:00–17:00 stub           |
| Whole card clickable       | Media + body                   |
| Clipboard fallback         | Copy link on insecure contexts |
| Single mobile Book CTA     | Nav vs sticky bar              |
| Top Featured count         | Label vs cards shown           |
| Gallery focus trap         | Lightbox a11y                  |

## Already solid

| Area                  | Status                                         |
| --------------------- | ---------------------------------------------- |
| CTAs                  | View details → detail Book Now                 |
| Empty packages chrome | Hidden nav/footer + /tours redirect            |
| Booking recovery      | Receipt, e-ticket, Find Booking, cancel window |
| Gates                 | Demo + maintenance on checkout                 |
| SEO / reviews         | Catalog SEO stack; Google slider gated         |
| Mobile WA             | Does not cover book bar                        |
| Tests                 | 21 storefront feature tests green              |

---

## Suggested tonight sequence

1. Safe-area + padding on detail sticky bars
2. Confirmation noindex + robots `Disallow /reservations/`
3. Empty /services redirect + sitemap guards
4. Empty-home CTA
5. Then push. Defer dual Livewire / hold countdown / copy nits to tomorrow.

_Source: `resources/views/storefront/**` · `StorefrontController` · local storefront tests (21 passed)_

_Desk / package-form work from today is out of scope — guest shop only._
