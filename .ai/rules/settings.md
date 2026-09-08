---
paths:
  - resources/views/pages/settings/⚡team.blade.php
  - resources/views/pages/settings/⚡brand.blade.php
  - resources/views/pages/settings/⚡reviews.blade.php
---

# Settings

## Confirm team invites in a modal; show desk coverage
Invite form submits promptInvite, which validates then opens confirm-team-invite. inviteTeammate is the send action. Role cards and the people table use OperatorUserRole::deskScope(), not a one-line blurb.

## Invite accordion starts closed
Invite someone is a closed accordion (Alpine inviteOpen false) so the people table leads. Open it when invite fields have errors. Remove is a rose bordered button, not ghost.

## Review Platform URL, not in-app reviews
The Reviews tab field is Review Platform (e.g. Google/Tripadvisor). It is listed on dashboard setup and required before post-trip review mail sends, unless Agency has a connected listing. There is no in-app reviews table or guest review form.

## No official website field
Brand settings has no Official Website Link. The shop is the operator website. social_links are Instagram, Facebook, TikTok, and YouTube only; do not persist or render settings.social_links.website.

## Desk forms are phone-ready
Brand, storefront, activity, and package create/edit must work on a phone. Do not restore x-desktop-only-notice or hidden lg:block on those pages. Team and payout bank stay gated. Custom domain DNS and tracking pixels may stay denser (tablet-better) but must still open.

## Phone logo dropzone is full width
Brand logo preview is w-full h-36 on phones, sm:w-24 sm:h-24 from sm up. Upload New Logo is w-full sm:w-auto. Do not restore a 96px square as the phone layout.

## Google listing connect on Reviews
The Reviews tab can connect a public Google listing by pasting a Maps link or searching a business name, then confirming. Store the snapshot on settings.google_place. Do not overwrite an existing Review Platform URL. Do not copy the listing into review_url. There is still no reviews desk page or in-app guest form.

## Google listing lookup on finish
Reviews tab Google listing search runs when the operator finishes the input: blur or Enter. Enter must set google_place_query from the field value, not only blur the input. Show the hint Press Enter to show the listing. Do not restore a Find listing button. Confirm still saves the listing.

## Google listing connect needs a modal
Connect this listing opens confirm-google-listing. The modal shows the selected listing name, address, and Google rating. confirmGooglePlace is the save action. Do not connect from the preview card without that modal.

## Google listing is Agency only
Only one Google listing per operator, and only the Agency plan (google_reviews) may connect it. Other plans see only the Review Platform URL. Do not show the lookup, confirm modal, or slider without that feature. Post-trip review mail uses the connected listing for Agency, and the Review Platform URL for every other plan.

## Hide Google search while a listing is connected
Agency has one Google listing. Hide the Maps search field, Enter hint, and connect preview while a listing is connected. Show them again only after Disconnect. Do not look up a second listing until the current one is cleared.

## Agency without a listing may use a review link
Agency with no listing chooses Connect a Google listing or Use a review link. Do not show the Review Platform field until they choose the link. Do not copy a connected listing into review_url. Other plans keep only the manual field. Dashboard setup calls the Agency step Reviews.

## Google listing disconnect needs a modal
Disconnect opens confirm-disconnect-google-listing with the connected listing name and address. disconnectGooglePlace is the remove action. Do not disconnect from the card without that modal.

## Reviews live on their own Storefront tab
Review Platform URL and Google listing connect live on /settings/reviews (review-settings.edit), not Brand. Do not add a Reviews sidebar item, command-palette link, or /reviews desk. Agency with no listing chooses Connect a Google listing or Use a review link. A connected listing hides that choice. Other plans see only the manual Review Platform field.
