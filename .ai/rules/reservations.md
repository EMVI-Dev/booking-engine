---
paths:
  - 'resources/views/pages/reservations/**'
---

# Reservations

## Pay-link modal stays copy-first and short
Pay-link modal is copy-first: date chips, pax stepper, recent-guest chips, email/notes behind extras. Success says copy/paste; WhatsApp is Open WhatsApp to paste, never send.

## Demo pay links stay off
Demo desk pay-link modal is sample-shop, not platform maintenance. Show Sample shop copy and keep Create link disabled. Demo must never generate a real pay link even when maintenance is off.

## Date chips sit above Date and Guests
Pay-link date chips sit in a full-width segmented row above Date and Guests so the picker and pax stepper share one baseline. Spots-left sits under Guests, not inside the Date column.

## Details button navigates to dedicated page
The Details button on phone cards and the desktop table navigates directly to the dedicated reservation page (`reservations.show` with `wire:navigate`). The inline details modal has been removed and all actions (confirmation, decline, completion, cancellation & refund) are centralized on the detail page.
