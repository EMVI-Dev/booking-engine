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

## Phone booking Details uses viewDetails
Phone booking cards must call viewDetails, same as the desktop table. There is no viewReservation method. Do not restore that wire:click name.
