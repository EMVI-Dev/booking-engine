<?php

use Illuminate\Support\Facades\Schedule;

// 1. Release matured operator wallet funds from escrow once trip date concludes
Schedule::command('wallet:release-escrows')->hourly();

// 2. Automatically expire stale 30-minute reservation holds to release capacity
Schedule::command('reservations:expire-holds')->everyFiveMinutes();

// 3. Mark past confirmed trips as completed
Schedule::command('trips:mark-completed')->daily();

// 4. Send upcoming pre-trip departure reminders 24-48 hours before trip date
Schedule::command('trips:send-departure-reminders')->hourly();

// 5. Send automated post-trip review request emails 12 hours after trip
Schedule::command('trips:send-review-requests')->hourly();
