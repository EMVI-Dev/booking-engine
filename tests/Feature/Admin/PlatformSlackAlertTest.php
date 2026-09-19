<?php

use App\Models\User;
use App\Services\OperatorActivitySlackNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->admin()->create([
        'name' => 'Platform Operator Lead',
        'email' => 'ops@travelengine.id',
    ]);
});

test('slack notifier test alert sends payload when webhook is configured', function () {
    Http::fake();

    config(['services.slack.operator_webhook_url' => 'https://hooks.slack.com/services/TEST/TOKEN/123']);

    $notifier = app(OperatorActivitySlackNotifier::class);
    expect($notifier->enabled())->toBeTrue();

    $sent = $notifier->testAlert('Test Admin', 'admin@example.com');
    expect($sent)->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://hooks.slack.com/services/TEST/TOKEN/123'
            && str_contains($request['text'], 'Platform Slack notification test');
    });
});

test('slack notifier test alert returns false when webhook is not configured', function () {
    Http::fake();

    config(['services.slack.operator_webhook_url' => '']);

    $notifier = app(OperatorActivitySlackNotifier::class);
    expect($notifier->enabled())->toBeFalse();

    $sent = $notifier->testAlert('Test Admin');
    expect($sent)->toBeFalse();

    Http::assertNothingSent();
});

test('platform settings component can trigger test slack alert', function () {
    Http::fake();

    config(['services.slack.operator_webhook_url' => 'https://hooks.slack.com/services/TEST/TOKEN/123']);

    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.platform')
        ->assertSet('slack_configured', true)
        ->call('testSlackAlert')
        ->assertDispatched('toast', type: 'success');

    Http::assertSentCount(1);
});
