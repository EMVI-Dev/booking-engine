<?php

use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PaymentMatchService;
use Livewire\Livewire;

test('the platform page lists guest payments that never reached an operator wallet', function () {
    $reservation = Reservation::factory()->create();
    Payment::factory()->create([
        'reservation_id' => $reservation->id,
        'amount' => 1500000,
        'gateway_ref' => 'INV-MISSING-WALLET',
        'status' => PaymentStatus::Paid,
    ]);

    app(PaymentMatchService::class)->unmatchedPaidCharges();

    $admin = User::factory()->create([
        'email' => 'admin@emvi.dev',
        'name' => 'Platform Admin',
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.platform.edit'))
        ->assertOk()
        ->assertSee('Paid, but not in a wallet yet')
        ->assertSee('INV-MISSING-WALLET')
        ->assertSee('A guest paid, but the money is not in the operator wallet yet.')
        ->assertSee('Check again');
});

test('admin can check again for guest payments that never reached a wallet', function () {
    $reservation = Reservation::factory()->create();
    Payment::factory()->create([
        'reservation_id' => $reservation->id,
        'amount' => 750000,
        'gateway_ref' => 'INV-CHECK-AGAIN',
        'status' => PaymentStatus::Paid,
    ]);

    $admin = User::factory()->create([
        'email' => 'admin@emvi.dev',
        'name' => 'Platform Admin',
        'is_admin' => true,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.platform')
        ->assertDontSee('INV-CHECK-AGAIN')
        ->call('checkUnmatchedPayments')
        ->assertSee('INV-CHECK-AGAIN')
        ->assertSee('A guest paid, but the money is not in the operator wallet yet.');
});

test('paid guest charges with a wallet earning are not flagged as unmatched', function () {
    $matched = Reservation::factory()->create();
    Payment::factory()->create([
        'reservation_id' => $matched->id,
        'amount' => 1500000,
        'gateway_ref' => 'INV-MATCHED-WALLET',
        'status' => PaymentStatus::Paid,
    ]);
    WalletTransaction::query()->create([
        'operator_id' => $matched->operator_id,
        'reservation_id' => $matched->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1500000.00,
        'fee_amount' => 0,
        'net_amount' => 1500000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Paid trip',
    ]);

    $unmatched = Reservation::factory()->create();
    Payment::factory()->create([
        'reservation_id' => $unmatched->id,
        'amount' => 750000,
        'gateway_ref' => 'INV-STILL-UNMATCHED',
        'status' => PaymentStatus::Paid,
    ]);

    $exceptions = app(PaymentMatchService::class)->unmatchedPaidCharges();

    expect(collect($exceptions)->pluck('invoice')->all())
        ->toContain('INV-STILL-UNMATCHED')
        ->not->toContain('INV-MATCHED-WALLET');
});
