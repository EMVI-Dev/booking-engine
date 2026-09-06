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

// 6. Process scheduled operator subscription downgrades upon billing cycle conclusion
Schedule::command('subscriptions:process-scheduled-changes')->daily();

// 7. Send automated subscription lapse reminders at 7 days, 3 days, and on the due date
Schedule::command('subscriptions:send-renewal-reminders')->daily();

// 8. Broadcast platform coupon codes to operators who meet eligibility thresholds (e.g. transaction volume)
Schedule::command('coupons:broadcast')->dailyAt('09:00');

// 9. After 3 extra days unpaid, return operators to the free plan
Schedule::command('subscriptions:return-unpaid-to-free')->daily();

// 10. Flag guest payments that never reached an operator wallet
Schedule::command('platform:match-payments')->dailyAt('03:00');

// 11. Record the padlock after Caddy has issued HTTPS for a connected address
Schedule::command('domains:probe-ssl')->everyFiveMinutes()->withoutOverlapping();
