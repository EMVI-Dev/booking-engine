<?php

use App\Enums\OperatorStatus;
use App\Http\Middleware\TrackOperatorActivity;
use App\Mail\OperatorAccountSuspendedInactivityMail;
use App\Mail\OperatorInactivityReminderMail;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

test('touchActivity updates last_active_at and throttles updates within 15 minutes', function () {
    $operator = Operator::factory()->create([
        'last_active_at' => now()->subHour(),
        'inactivity_reminder_sent_at' => now()->subDay(),
    ]);

    $updated = $operator->touchActivity();
    expect($updated)->toBeTrue()
        ->and($operator->fresh()->last_active_at)->not->toBeNull()
        ->and($operator->fresh()->inactivity_reminder_sent_at)->toBeNull();

    // Calling immediately again within 15 minutes should be throttled
    $throttled = $operator->touchActivity();
    expect($throttled)->toBeFalse();

    // With force=true, it should update
    $forced = $operator->touchActivity(force: true);
    expect($forced)->toBeTrue();
});

test('middleware touches operator activity for authenticated users but ignores admin impersonation', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create([
        'last_active_at' => now()->subHours(2),
    ]);
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $middleware = new TrackOperatorActivity;

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);
    $request->attributes->set('current_operator', $operator);

    $middleware->handle($request, fn () => new Response('OK'));

    expect($operator->fresh()->last_active_at->diffInMinutes(now()))->toBeLessThan(2);

    // Test with admin impersonation
    $admin = User::factory()->admin()->create();
    $adminRequest = Request::create('/dashboard', 'GET');
    $adminRequest->setUserResolver(fn () => $admin);
    $adminRequest->attributes->set('current_operator', $operator);
    session(['admin_impersonated_operator_id' => $operator->id]);

    $operator->updateQuietly(['last_active_at' => now()->subHours(5)]);
    $middleware->handle($adminRequest, fn () => new Response('OK'));

    // Should remain untouched at 5 hours ago
    expect($operator->fresh()->last_active_at->diffInHours(now()))->toBeGreaterThanOrEqual(4);
});

test('check-inactivity command sends 30-day reminder to inactive operators and records sent timestamp', function () {
    Mail::fake();
    Http::fake();

    $operator = Operator::factory()->create([
        'name' => 'Idle Sea Tours',
        'status' => OperatorStatus::Approved,
        'booking_notification_email' => 'idle@example.test',
        'last_active_at' => now()->subDays(35),
        'inactivity_reminder_sent_at' => null,
    ]);

    $activeOperator = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'last_active_at' => now()->subDays(5),
    ]);

    $this->artisan('operators:check-inactivity')
        ->expectsOutputToContain('Reminded: 1, Suspended: 0')
        ->assertSuccessful();

    Mail::assertQueued(OperatorInactivityReminderMail::class, function (OperatorInactivityReminderMail $mail) use ($operator) {
        return $mail->operator->id === $operator->id
            && $mail->hasTo('idle@example.test')
            && $mail->inactiveDays >= 35;
    });

    expect($operator->fresh()->inactivity_reminder_sent_at)->not->toBeNull();

    // Running a second time should not send again because already reminded
    Mail::fake();
    $this->artisan('operators:check-inactivity')
        ->expectsOutputToContain('Reminded: 0, Suspended: 0')
        ->assertSuccessful();

    Mail::assertNothingQueued();
});

test('check-inactivity command suspends operators inactive for 90 days (3 months)', function () {
    Mail::fake();
    Http::fake();
    config(['services.slack.operator_webhook_url' => 'https://hooks.slack.com/services/TEST/TOKEN/123']);

    $operator = Operator::factory()->create([
        'name' => 'Abandoned Charters',
        'slug' => 'abandoned-charters',
        'status' => OperatorStatus::Approved,
        'booking_notification_email' => 'abandoned@example.test',
        'last_active_at' => now()->subDays(95),
    ]);

    $this->artisan('operators:check-inactivity')
        ->expectsOutputToContain('Reminded: 0, Suspended: 1')
        ->assertSuccessful();

    expect($operator->fresh()->status)->toBe(OperatorStatus::Suspended);

    Mail::assertQueued(OperatorAccountSuspendedInactivityMail::class, function (OperatorAccountSuspendedInactivityMail $mail) use ($operator) {
        return $mail->operator->id === $operator->id
            && $mail->hasTo('abandoned@example.test')
            && $mail->inactiveDays >= 95;
    });

    Http::assertSent(fn ($request) => str_contains($request['text'], 'Operator status:')
        && str_contains($request['text'], 'Abandoned Charters'));
});
