<?php

use App\Enums\OperatorStatus;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Plan::seedDefaultPlans();
    $this->proPlan = Plan::where('slug', 'growth')->first();
});

test('command sends renewal reminders at 7 days, 3 days, and on the due date', function () {
    Mail::fake();

    // 1. Operator expiring in 7 days
    $user7 = User::factory()->create(['email' => 'operator7@example.com']);
    $op7 = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'plan_id' => $this->proPlan->id,
        'billing_email' => 'billing7@example.com',
        'plan_expires_at' => now()->addDays(7)->startOfDay(),
    ]);
    $op7->users()->attach($user7->id, ['role' => 'owner']);

    // 2. Operator expiring in 3 days
    $user3 = User::factory()->create(['email' => 'operator3@example.com']);
    $op3 = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'plan_id' => $this->proPlan->id,
        'billing_email' => 'billing3@example.com',
        'plan_expires_at' => now()->addDays(3)->startOfDay(),
    ]);
    $op3->users()->attach($user3->id, ['role' => 'owner']);

    // 3. Operator expiring today (0 days / due date)
    $user0 = User::factory()->create(['email' => 'operator0@example.com']);
    $op0 = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'plan_id' => $this->proPlan->id,
        'billing_email' => 'billing0@example.com',
        'plan_expires_at' => now()->startOfDay(),
    ]);
    $op0->users()->attach($user0->id, ['role' => 'owner']);

    // 4. Operator expiring in 15 days (should NOT receive reminder)
    $user15 = User::factory()->create(['email' => 'operator15@example.com']);
    $op15 = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'plan_id' => $this->proPlan->id,
        'billing_email' => 'billing15@example.com',
        'plan_expires_at' => now()->addDays(15)->startOfDay(),
    ]);
    $op15->users()->attach($user15->id, ['role' => 'owner']);

    $this->artisan('subscriptions:send-renewal-reminders')
        ->assertSuccessful();

    // Verify 7-day reminder sent
    Mail::assertSent(SubscriptionRenewalReminderMail::class, function ($mail) {
        return $mail->hasTo('billing7@example.com') && $mail->daysRemaining === 7;
    });

    // Verify 3-day reminder sent
    Mail::assertSent(SubscriptionRenewalReminderMail::class, function ($mail) {
        return $mail->hasTo('billing3@example.com') && $mail->daysRemaining === 3;
    });

    // Verify 0-day (due date) reminder sent
    Mail::assertSent(SubscriptionRenewalReminderMail::class, function ($mail) {
        return $mail->hasTo('billing0@example.com') && $mail->daysRemaining === 0;
    });

    // Verify 15-day operator was NOT sent
    Mail::assertNotSent(SubscriptionRenewalReminderMail::class, function ($mail) {
        return $mail->hasTo('billing15@example.com');
    });
});
