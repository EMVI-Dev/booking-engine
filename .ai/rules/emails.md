---
paths:
  - 'resources/views/emails/**'
---

# Emails

## Email chrome: operator vs platform
Guest mail uses the operator brand colour, brand_foreground_color for text on that colour, and the operator logo. Platform-to-operator mail (new booking, subscription) uses platform yellow #FFEF4D and ink #101730, plus the platform favicon — not indigo/purple. Review CTA is "Leave a review"; do not name Google, TripAdvisor, or any other platform because the URL can be anything. Header chrome lives in x-email.brand-header. Logo src must be an absolute URL.

## Agency review mail uses the listing
Post-trip review mail for Agency with a connected Google listing uses that listing's write-a-review URL and names the listing. Other plans use the Review Platform URL only. CTA stays Leave a review. Do not name Google or TripAdvisor in the generic copy.

## Review mail follows the Reviews tab choice
Agency with a connected Google listing uses that listing write-a-review URL and names the listing. Agency without a listing, and every other plan, use the Review Platform URL from the Reviews tab. CTA stays Leave a review.
