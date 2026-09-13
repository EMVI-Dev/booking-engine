---
paths:
  - 'resources/views/pages/guests/**'
---

# Guests

## Guest CRM: directory vs profile
Directory (guests.index) is scan-only: last/next trip columns, Duplicate? badge, CSV export of the filtered query, global SQL sort (including lifetime spend via payments subquery). The only link to guests.show is an eye-icon View button (not the name or row). CTAs, edit modal, trip history, and merge live on guests.show. Gate guest_crm on mount/save/merge/export; foreign-operator guests 404.
