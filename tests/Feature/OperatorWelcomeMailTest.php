<?php

use App\Mail\OperatorWelcomeMail;
use App\Models\Operator;
use App\Models\User;
use App\Services\OperatorOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('operator registration queues a welcome email with slug and login url', function () {
    Mail::fake();
    Http::fake();

    $result = app(OperatorOnboardingService::class)->registerOperator([
        'name' => 'Wayan Sudarma',
        'email' => 'wayan@example.com',
        'password' => 'password123',
        'operator_name' => 'Nusa Penida Charters',
        'slug' => 'nusa-penida-charters',
    ]);

    Mail::assertQueued(OperatorWelcomeMail::class, function (OperatorWelcomeMail $mail) use ($result) {
        expect($mail->operator->id)->toBe($result['operator']->id)
            ->and($mail->user->id)->toBe($result['user']->id)
            ->and($mail->hasTo('wayan@example.com'))->toBeTrue();

        $rendered = $mail->render();
        expect($rendered)->toContain('Nusa Penida Charters')
            ->and($rendered)->toContain('nusa-penida-charters')
            ->and($rendered)->toContain($result['operator']->getDeskUrl())
            ->and($rendered)->toContain($result['operator']->getStorefrontUrl())
            ->and($rendered)->toContain('wayan@example.com');

        return true;
    });
});

test('operator welcome email renders correctly for an operator', function () {
    $operator = Operator::factory()->create([
        'name' => 'Komodo Diving Co',
        'slug' => 'komodo-diving-co',
        'booking_notification_email' => 'bookings@komodo.test',
    ]);
    $user = User::factory()->create([
        'name' => 'Captain Made',
        'email' => 'made@komodo.test',
    ]);

    $mail = new OperatorWelcomeMail($operator, $user);

    $appName = config('app.name');
    expect($mail->envelope()->subject)->toBe("Welcome to {$appName} - Your Tour Operator Account is Ready");

    $html = $mail->render();
    expect($html)->toContain('Komodo Diving Co')
        ->and($html)->toContain('komodo-diving-co')
        ->and($html)->toContain('made@komodo.test')
        ->and($html)->toContain($operator->getDeskUrl())
        ->and($html)->toContain($operator->getStorefrontUrl());
});
