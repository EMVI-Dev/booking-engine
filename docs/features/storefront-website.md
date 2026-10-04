# Storefront website extras

_Last reviewed: 2026-10-05 (separate pages)_

TravelEngine promises each operator a simple website with booking and payment built in. Most users are freelance guides working alone, so every extra here follows one rule: **set it once, and it keeps itself right**. Nothing needs regular writing, and empty sections never show.

## Pages
Gallery, FAQ and Contact each have their own storefront page: `/gallery`, `/faq` and `/contact` (routes `storefront.gallery|faq|contact`). The home page links to them instead of showing everything in one long scroll.

`StorefrontPagesService::pagesFor()` is the one answer to "does this shop have the page?". The navbar, footer, home links, the page routes, `sitemap.xml` and `llms.txt` all use it, so a page is never linked or indexed while empty:

| Page | Exists when | Otherwise |
| :--- | :--- | :--- |
| Gallery | at least one visible photo | 404, not linked, not in the sitemap |
| FAQ | at least one answer not hidden | 404, not linked, not in the sitemap |
| Contact | the contact form is open, or the shop has a WhatsApp number | 404, not linked, not in the sitemap |

None of these pages exist on the platform root.

**Search markup (JSON-LD):** every page carries breadcrumbs and the shop's `TravelAgency` node.
- `/gallery`: an `ImageGallery` with every visible photo as an `ImageObject` (URL, caption, credit), and the first photo as `primaryImageOfPage`.
- `/faq`: the only `FAQPage` on the site. The home page no longer carries it, so search engines see one FAQ, on the page that shows it.
- `/contact`: a `ContactPage` whose `mainEntity` is the shop.
- `og:type` is `website` on all three.
- The private booking notification email is never shown on the page or in the markup.

`llms.txt` links the pages a shop has, and `llms-full.txt` includes the FAQ answers (Agency plan, as before).

, in three tabs: **Gallery**, **FAQ** and **Contact**. They are one page (`settings/website?tab=gallery|faq|contact`), and `settings/gallery`, `settings/faq` and `settings/contact` redirect to the matching tab. Plan limits are in [`../commercial/plans-and-pricing.md`](../commercial/plans-and-pricing.md).

## Gallery
- **Who:** every plan. The photo limit is set per plan in Admin → Plans (`plans.gallery_photo_limit`), and defaults fill a 3-column grid:

  | Plan | Photos |
  | :--- | :--- |
  | Starter | 9 |
  | Growth | 18 |
  | Agency | 36 |

- **Uploads:** JPG, PNG or WebP up to 10 MB. Every photo is resized to 1600 px wide, converted to WebP and stored on R2 under `operators/{id}/gallery/` (`MediaStore`).
- **Editing:** an optional caption (also the image alt text), reordering, and removal. Removing a photo deletes its file.
- **Downgrade:** photos beyond the new limit are kept but hidden until the operator upgrades or removes some. Nothing is deleted.
- **Code:** `StorefrontGalleryService`, `OperatorGalleryPhoto`, `storefront/gallery`.

## FAQ
- **Who:** every plan.
- **Generated answers:** ready on day one, built from settings the operator already has:
  - payment methods
  - instant or manual confirmation
  - e-ticket
  - cancellation window, when every published trip shares one
  - finding a booking
  - contact
  They stay correct as those settings change.
- **Operator control:** they can untick any generated answer and add up to 15 of their own questions. Stored in `settings.faq = {hidden, items}`.
- **Search markup:** FAQPage structured data on `/faq` only (`StorefrontSeoService::faqGraph()`).
- **Code:** `StorefrontFaqService`, `storefront/faq`.

## Contact and private/group enquiry form
- **Who:** paid plans only (`contact_form` feature on Growth and Agency).
- **Off by default.** Guests keep using the floating WhatsApp button. The operator turns the form on in settings (`settings.contact_form.enabled`).
- **One form:** name, WhatsApp, optional email and a message, with a choice of "A question" or "Private / group trip". The group option adds a preferred date and group size.
- **Delivery:**
  - Every enquiry is saved in the desk under **Enquiries**, with an unread badge and a **Reply on WhatsApp** button that has a greeting filled in.
  - It is emailed only if the operator turned email on. It goes to the address they chose, or their booking email, and Reply-To is the guest's email when given.
- **Spam protection, all free:** a hidden honeypot field, a minimum fill time of 3 seconds, and a limit of 3 enquiries per visitor per hour. Bots get a fake success and nothing is stored. There's no CAPTCHA, because reCAPTCHA is only free up to 10,000 checks a month. If spam ever gets through, Cloudflare Turnstile (free) can be added.
- **Who sees enquiries:** teammates with `manageReservations`.
- **Code:** `EnquiryService`, `Enquiry`, `livewire/storefront/⚡contact-form`, `pages/enquiries/⚡index`, `OperatorEnquiryMail`.

## Cookie notice
- **When it appears:** only on pages with a tracker, meaning an operator's GA, GTM or Meta Pixel (Growth and up), or the platform's own GA on the marketing pages.
- **How consent works:**
  - Tracking scripts are printed inert (`type="text/plain" data-consent="analytics"`) and run only after **Accept**.
  - **Decline** means no tracking script loads at all. The Meta noscript pixel was removed, because it tracked without consent.
- **Memory:** the choice lasts 180 days (`te_consent` cookie). Shops with a tracker show **Cookie settings** in the footer to change it.
- **Code:** `partials/cookie-consent`, `storefront/partials/tracking-scripts`, `partials/platform-tracking`.

## Google listing (Agency)
This uses only Google's free services: the Maps Embed card plus "Read our reviews on Google" and "Leave a review" links. See [`../engineering/integrations.md`](../engineering/integrations.md).

---

## UI brief for Antigravity
The backend and plain working markup are done. Please restyle in the storefront and desk design language and keep the behaviour.

**Storefront pages** (`storefront/gallery`, `storefront/faq`, `storefront/contact`). The home page shows trips → Google listing → about → links to the pages a shop has.
1. **Gallery:** a 3-column square grid on every screen size. Tapping opens a lightbox (Alpine) with previous/next, Esc to close, and the caption. Keep `loading="lazy"`.
2. **FAQ:** an accordion built on `<details>` / `<summary>`, so it works without JavaScript. Keep the question text as the summary.
3. **Contact form:**
   - A segmented choice: "A question" / "Private / group trip". Date and group size appear only for the group choice.
   - The success state says the reply comes on WhatsApp.
   - **Keep the off-screen honeypot exactly as is.** Never `display:none` and never `type="hidden"`, or bots skip it.
4. **Cookie banner:** the JavaScript builds it with inline styles. Restyle it if you like, but keep the element id `te-consent-banner` and the two buttons `data-choice="granted"` / `data-choice="denied"`. Decline must stay as visible as Accept.

**Settings → Gallery, FAQ & Contact** (`pages/settings/⚡website`):
- **Gallery:** an upload area with drag and drop if you like (Livewire `uploads`, multiple), a thumbnail grid with caption field, reorder controls and remove, and the "x of y photos" counter. When the gallery is full, show an upgrade hint.
- **FAQ:** the generated answers as a checklist (ticked = shown), then the operator's own questions as an add/remove repeater, then Save.
- **Contact:** on the free plan, an upgrade hint. On paid plans, the toggle → the "also email me" toggle → an optional email field.

**Desk → Enquiries** (`pages/enquiries/⚡index`): a card list with a "New" badge for unread, the type, contact details, the message, and the actions **Reply on WhatsApp** (primary), Mark as read and Delete. The sidebar item shows the unread count.
