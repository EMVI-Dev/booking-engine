<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Schedule
|--------------------------------------------------------------------------
|
| Times follow config('app.timezone') (WITA). Jobs that move money or change
| booking state never overlap and run on one server only, so a slow run or a
| second app server cannot release, cancel or charge the same thing twice.
| onOneServer() needs a shared cache store (database or redis).
|
*/

// 1. Release matured operator wallet funds from escrow once trip date concludes
Schedule::command('wallet:release-escrows')->hourly()->withoutOverlapping()->onOneServer();

// 2. Automatically expire stale 30-minute reservation holds to release capacity
Schedule::command('reservations:expire-holds')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// 3. Mark past confirmed trips as completed (just after midnight WITA)
Schedule::command('trips:mark-completed')->dailyAt('00:15')->withoutOverlapping()->onOneServer();

// 4. Send upcoming pre-trip departure reminders 24-48 hours before trip date
Schedule::command('trips:send-departure-reminders')->hourly()->withoutOverlapping()->onOneServer();

// 5. Send automated post-trip review request emails 12 hours after trip
Schedule::command('trips:send-review-requests')->hourly()->withoutOverlapping()->onOneServer();

// 6. Process scheduled operator subscription downgrades upon billing cycle conclusion
Schedule::command('subscriptions:process-scheduled-changes')->dailyAt('00:30')->withoutOverlapping()->onOneServer();

// 7. Send automated subscription lapse reminders at 7 days, 3 days, and on the due date
Schedule::command('subscriptions:send-renewal-reminders')->dailyAt('09:00')->withoutOverlapping()->onOneServer();

// 8. Broadcast platform coupon codes to operators who meet eligibility thresholds (e.g. transaction volume)
Schedule::command('coupons:broadcast')->dailyAt('09:30')->withoutOverlapping()->onOneServer();

// 9. After 3 extra days unpaid, return operators to the free plan
Schedule::command('subscriptions:return-unpaid-to-free')->dailyAt('00:45')->withoutOverlapping()->onOneServer();

// 10. Flag guest payments that never reached an operator wallet
Schedule::command('platform:match-payments')->dailyAt('03:00')->withoutOverlapping()->onOneServer();

// 11. Record the padlock after Caddy has issued HTTPS for a connected address
Schedule::command('domains:probe-ssl')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// 12. Refresh cached Google listing reviews so the shop does not call Google on each page view
Schedule::command('google:refresh-reviews')->dailyAt('04:00')->withoutOverlapping()->onOneServer();

// 13. Reset the public demo operator so look-around data stays clean
Schedule::command('demo:refresh')->dailyAt('03:30')->withoutOverlapping()->onOneServer();

// 14. Check operator activity: remind after 30 days, suspend after 90 days (3 months)
Schedule::command('operators:check-inactivity')->dailyAt('10:00')->withoutOverlapping()->onOneServer();
