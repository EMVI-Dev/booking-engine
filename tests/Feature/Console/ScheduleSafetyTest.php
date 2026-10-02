<?php

use App\Services\CancellationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('the app runs on Bali time', function () {
    expect(config('app.timezone'))->toBe('Asia/Makassar')
        ->and(now()->getTimezone()->getName())->toBe('Asia/Makassar');
});

test('every scheduled job is locked against overlap and runs on one server', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events)->not->toBeEmpty();

    $events->each(function (Event $event): void {
        expect($event->withoutOverlapping)->toBeTrue("{$event->command} may overlap")
            ->and($event->onOneServer)->toBeTrue("{$event->command} may run on every server");
    });

    $commands = $events->map(fn (Event $event): string => (string) $event->command)->implode("\n");

    foreach (['wallet:release-escrows', 'reservations:expire-holds', 'trips:mark-completed', 'subscriptions:return-unpaid-to-free'] as $moneyCommand) {
        expect($commands)->toContain($moneyCommand);
    }
});

test('the free-cancel cutoff and trip day follow WITA, not UTC', function () {
    // 23:30 UTC on 9 Oct is already 07:30 on 10 Oct in Bali.
    $this->travelTo(CarbonImmutable::parse('2026-10-09 23:30:00', 'UTC'));

    expect(today()->toDateString())->toBe('2026-10-10')
        ->and(CancellationPolicy::isFree('2026-10-10', 0))->toBeFalse()
        ->and(CancellationPolicy::isFree('2026-10-11', 24))->toBeFalse()
        ->and(CancellationPolicy::isFree('2026-10-12', 24))->toBeTrue();
});
