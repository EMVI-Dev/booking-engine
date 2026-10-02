---
paths:
  - 'resources/views/pages/guests/**'
---

# Guests

## Guest CRM: directory vs profile
Directory (guests.index) is scan-only: last/next trip columns, Duplicate? badge, CSV export of the filtered query, global SQL sort (including lifetime spend via payments subquery). The only link to guests.show is an eye-icon View button (not the name or row). CTAs, edit modal, trip history, and merge live on guests.show. Gate guest_crm on mount/save/merge/export; foreign-operator guests 404.

## Guest CRM profile layout and quick action conventions
Guest show view is structured as a CRM profile card layout: top left sidebar with initials avatar, VIP/repeat badges, contact quick actions (WhatsApp, Email, Call), notes, and tags; right side contains lifetime value KPI cards, trip/reservation history with status filter, and duplicate profile detection.
