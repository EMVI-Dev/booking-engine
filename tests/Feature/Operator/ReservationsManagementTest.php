<?php

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DokuPaymentService;
use App\Services\WalletService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create();
    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);
});

test('unauthenticated users cannot view reservations page', function () {
    $this->get(route('reservations.index'))
        ->assertRedirect(route('login'));
});

test('operator can view reservations page with metric counters', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $confirmed = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Budi Santoso',
    ]);

    Payment::factory()->paid()->create([
        'reservation_id' => $confirmed->id,
        'amount' => 1500000,
    ]);

    $pending = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::PaymentPending,
    ]);

    $this->get(route('reservations.index'))
        ->assertOk()
        ->assertSee('Bookings & Reservations')
        ->assertSee('Budi Santoso')
        ->assertSee('Confirmed');

    Livewire::test('pages::reservations.index')
        ->assertSee('Budi Santoso')
        ->assertSee('Rp 1.500.000');
});

test('operator only sees their own reservations and not other operators reservations', function () {
    $this->actingAs($this->user);

    $otherOperator = Operator::factory()->create();
    $otherPackage = Package::factory()->create(['operator_id' => $otherOperator->id]);

    $myPackage = Package::factory()->create(['operator_id' => $this->operator->id]);

    Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $myPackage->id,
        'guest_name' => 'My Guest Alice',
    ]);

    Reservation::factory()->create([
        'operator_id' => $otherOperator->id,
        'bookable_type' => 'package',
        'bookable_id' => $otherPackage->id,
        'guest_name' => 'Other Operator Guest Bob',
    ]);

    Livewire::test('pages::reservations.index')
        ->assertSee('My Guest Alice')
        ->assertDontSee('Other Operator Guest Bob');
});

test('operator can filter reservations by status', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Confirmed Guest John',
    ]);

    Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::PaymentPending,
        'guest_name' => 'Pending Guest Mary',
    ]);

    Livewire::test('pages::reservations.index')
        ->set('statusFilter', 'confirmed')
        ->assertSee('Confirmed Guest John')
        ->assertDontSee('Pending Guest Mary')
        ->set('statusFilter', 'payment_pending')
        ->assertSee('Pending Guest Mary')
        ->assertDontSee('Confirmed Guest John');
});

test('operator can search reservations by guest name or email', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Wayan Sukadana',
        'guest_email' => 'wayan@example.com',
    ]);

    Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Ketut Mangku',
        'guest_email' => 'ketut@example.com',
    ]);

    Livewire::test('pages::reservations.index')
        ->set('search', 'Wayan')
        ->assertSee('Wayan Sukadana')
        ->assertDontSee('Ketut Mangku')
        ->set('search', 'ketut@example.com')
        ->assertSee('Ketut Mangku')
        ->assertDontSee('Wayan Sukadana');
});

test('operator can update reservation status and save notes', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::PaymentPending,
        'notes' => 'Original note',
    ]);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('confirmStatusTransition', 'confirmed')
        ->call('executeStatusTransition')
        ->assertDispatched('toast');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->set('agentNote', 'VIP pickup requested at hotel lobby 7 AM')
        ->call('saveNotes')
        ->assertDispatched('toast');

    expect($reservation->fresh()->notes)->toBe('VIP pickup requested at hotel lobby 7 AM');
});

test('operator can configure booking confirmation mode in storefront settings', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::settings.storefront')
        ->assertSet('booking_confirmation_mode', 'automatic')
        ->set('booking_confirmation_mode', 'manual')
        ->call('updateStorefrontSettings')
        ->assertDispatched('storefront-updated');

    $this->operator->refresh();
    expect($this->operator->isManualConfirmationEnabled())->toBeTrue()
        ->and($this->operator->getBookingConfirmationMode())->toBe('manual');
});

test('manual confirmation mode sets paid reservation to pending_confirmation until operator approves', function () {
    $this->operator->update([
        'settings' => [
            'storefront' => [
                'booking_confirmation_mode' => 'manual',
            ],
        ],
    ]);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::PaymentPending,
    ]);

    $payment = Payment::factory()->create([
        'reservation_id' => $reservation->id,
        'gateway_ref' => 'INV-MANUAL-TEST-001',
    ]);

    $service = app(DokuPaymentService::class);
    $service->processNotification([
        'order' => ['invoice_number' => 'INV-MANUAL-TEST-001'],
        'transaction' => ['status' => 'SUCCESS'],
    ]);

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::PendingConfirmation);

    // Operator approves via dedicated details page
    $this->actingAs($this->user);
    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('confirmStatusTransition', 'confirmed')
        ->call('executeStatusTransition');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('reservations automatically generate unique code and can be searched by code', function () {
    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Dewi Sartika',
    ]);

    expect($reservation->code)->not->toBeEmpty()
        ->and($reservation->code)->toStartWith('RSV-');

    $this->actingAs($this->user);
    Livewire::test('pages::reservations.index')
        ->set('search', $reservation->code)
        ->assertSee('Dewi Sartika')
        ->assertSee($reservation->code);
});

test('operator cannot decline a paid reservation', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
    ]);

    Payment::factory()->paid()->create([
        'reservation_id' => $reservation->id,
        'amount' => 1000000,
    ]);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('confirmStatusTransition', 'declined')
        ->assertSet('showConfirmStatusModal', false)
        ->assertDispatched('toast');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('operator cannot mark a reservation completed before scheduled trip date', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'requested_date' => now()->addDays(5)->toDateString(),
    ]);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('confirmStatusTransition', 'completed')
        ->assertSet('showConfirmStatusModal', false)
        ->assertDispatched('toast');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('operator can mark a confirmed reservation completed on or after scheduled date', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'requested_date' => now()->toDateString(),
    ]);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('confirmStatusTransition', 'completed')
        ->assertSet('showConfirmStatusModal', true)
        ->call('executeStatusTransition')
        ->assertDispatched('toast');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Completed);
});

test('dedicated reservation page supports toggling note editing mode and saving notes', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'notes' => 'Existing internal note',
    ]);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->assertSet('isEditingNotes', false)
        ->call('startEditingNotes')
        ->assertSet('isEditingNotes', true)
        ->set('agentNote', 'Updated note for driver')
        ->call('saveNotes')
        ->assertSet('isEditingNotes', false)
        ->assertDispatched('toast');

    expect($reservation->fresh()->notes)->toBe('Updated note for driver');
});

test('operator cancelling a paid confirmed booking automatically refunds guest and cancels escrow', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'requested_date' => now()->addDays(3)->toDateString(),
    ]);

    $payment = Payment::factory()->paid()->create([
        'reservation_id' => $reservation->id,
        'amount' => 1000000,
        'gateway_ref' => 'INV-TEST-OP-REFUND',
    ]);

    $trx = WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $reservation->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1000000.00,
        'fee_amount' => 0.00,
        'net_amount' => 1000000.00,
        'status' => WalletTransactionStatus::PendingEscrow,
        'available_at' => now()->addDays(3),
        'description' => 'Pending trip',
    ]);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('confirmStatusTransition', 'cancelled')
        ->assertSet('showConfirmStatusModal', true)
        ->call('executeStatusTransition')
        ->assertDispatched('toast');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($trx->fresh()->status)->toBe(WalletTransactionStatus::Cancelled)
        ->and($this->operator->getPendingEscrowBalance())->toBe(0.0);
});

test('operator marking confirmed reservation completed immediately releases pending escrow to available balance', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'requested_date' => now()->toDateString(),
    ]);

    $trx = WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $reservation->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1000000.00,
        'fee_amount' => 0.00,
        'net_amount' => 1000000.00,
        'status' => WalletTransactionStatus::PendingEscrow,
        'available_at' => now()->toDateString(),
        'description' => 'Tour departed today',
    ]);

    expect($this->operator->getAvailableBalance())->toBe(0.0)
        ->and($this->operator->getPendingEscrowBalance())->toBe(1000000.0);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('confirmStatusTransition', 'completed')
        ->assertSet('showConfirmStatusModal', true)
        ->call('executeStatusTransition')
        ->assertDispatched('toast');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Completed)
        ->and($trx->fresh()->status)->toBe(WalletTransactionStatus::Cleared)
        ->and($this->operator->getAvailableBalance())->toBe(1000000.0)
        ->and($this->operator->getPendingEscrowBalance())->toBe(0.0);
});

test('operator can view dedicated reservation details page with complete info', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Bali Hidden Canyon Snorkeling',
    ]);

    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Sarah Connor',
        'guest_email' => 'sarah@resistance.org',
        'guest_contact' => '+6281234567890',
        'requested_date' => now()->addDays(5)->toDateString(),
    ]);

    $payment = Payment::factory()->paid()->create([
        'reservation_id' => $reservation->id,
        'amount' => 1500000,
        'gateway' => 'DOKU',
        'gateway_ref' => 'INV-SARAH-001',
    ]);

    $this->get(route('reservations.show', $reservation))
        ->assertOk()
        ->assertSee('Sarah Connor')
        ->assertSee('#'.$reservation->code)
        ->assertSee('Bali Hidden Canyon Snorkeling')
        ->assertSee('1.500.000')
        ->assertSee('INV-SARAH-001')
        ->assertSee('E-Ticket')
        ->assertSee('Receipt');
});

test('operator cannot view another operator reservation detail page', function () {
    $this->actingAs($this->user);

    $otherOperator = Operator::factory()->create();
    $otherReservation = Reservation::factory()->create([
        'operator_id' => $otherOperator->id,
    ]);

    $this->get(route('reservations.show', $otherReservation))
        ->assertNotFound();
});

test('operator can update notes and status on dedicated reservation page', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'status' => ReservationStatus::PaymentPending,
    ]);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->set('agentNote', 'Pickup at Grand Hyatt Nusa Dua lobby at 7 AM')
        ->call('saveNotes')
        ->assertDispatched('toast');

    expect($reservation->fresh()->notes)->toBe('Pickup at Grand Hyatt Nusa Dua lobby at 7 AM');

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('confirmStatusTransition', 'confirmed')
        ->assertSet('showConfirmStatusModal', true)
        ->call('executeStatusTransition')
        ->assertDispatched('toast');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('reservations index details button navigates to dedicated page without modal or inline status actions', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'guest_name' => 'Direct Page Guest',
        'status' => ReservationStatus::PaymentPending,
    ]);

    $this->get(route('reservations.index'))
        ->assertOk()
        ->assertSee('Direct Page Guest')
        ->assertSee(route('reservations.show', $reservation), false)
        ->assertDontSee('wire:click="viewDetails(', false)
        ->assertDontSee('confirmStatusTransition', false);
});

test('reservation detail page provides trip info modal and does not show inline inclusions', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Mount Batur Sunrise Trek',
        'inclusions' => ['Flashlight & Trekking Pole', 'Breakfast at Summit'],
        'exclusions' => ['Personal Expenses', 'Hotel Transfer'],
    ]);

    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'terms_snapshot' => [
            'inclusions' => ['Flashlight & Trekking Pole', 'Breakfast at Summit'],
            'exclusions' => ['Personal Expenses', 'Hotel Transfer'],
            'cancellation_terms' => 'Full refund 24 hours prior',
        ],
    ]);

    // On initial page render, inline inclusions should not be present
    $this->get(route('reservations.show', $reservation))
        ->assertOk()
        ->assertSee('Trip Info')
        ->assertDontSee('What is Included:');

    // Livewire component test for opening and closing Trip Info modal
    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->assertSet('showTripInfoModal', false)
        ->call('openTripInfoModal')
        ->assertSet('showTripInfoModal', true)
        ->assertSee('Flashlight & Trekking Pole')
        ->assertSee('Breakfast at Summit')
        ->assertSee('Personal Expenses')
        ->assertSee('Full refund 24 hours prior')
        ->call('closeTripInfoModal')
        ->assertSet('showTripInfoModal', false);
});

test('reservations with promo coupon calculate net operator earning correctly and display coupon usage to operator', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Ubud Waterfall Escape',
        'price' => 100000,
    ]);

    $reservation = Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'product',
        'bookable_id' => $product->id,
        'pax_count' => 1,
        'terms_snapshot' => [
            'subtotal' => 100000,
            'service_fee' => 5000,
            'service_fee_rate' => 0.05,
            'coupon_code' => 'EMVITEST2026',
            'discount_amount' => 99000,
            'total_price' => 6000,
        ],
    ]);

    // Test payment session creation sets net agent share to 1000 (100k - 99k), NOT 100k
    $paymentService = app(DokuPaymentService::class);
    $session = $paymentService->createPaymentSession($reservation, 6000.0);
    $payment = $session['payment'];

    expect($payment->split_details['operator_amount'])->toEqual(1000)
        ->and($payment->split_details['guest_service_fee'])->toEqual(5000)
        ->and($payment->split_details['discount_amount'])->toEqual(99000)
        ->and($payment->split_details['coupon_code'])->toBe('EMVITEST2026');

    // Simulate payment settled and earning credited
    $payment->update(['status' => PaymentStatus::Paid]);
    $earning = app(WalletService::class)->creditBookingPayment($payment);

    expect($earning->gross_amount)->toBe('6000.00')
        ->and($earning->fee_amount)->toBe('5000.00')
        ->and($earning->net_amount)->toBe('1000.00');

    // Test operator reservation details page displays coupon badge and discount breakdown
    $this->get(route('reservations.show', $reservation))
        ->assertOk()
        ->assertSee('EMVITEST2026')
        ->assertSee('99.000')
        ->assertSee('100.000');
});
