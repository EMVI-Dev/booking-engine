<?php

use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\Reservation;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DokuPaymentService;
use App\Services\SubscriptionProrationService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();

    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
        'bank_provider' => 'BCA',
        'bank_account_name' => 'Real Owner',
        'bank_account_number' => '1234567890',
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'price' => 1000000.00,
        'advance_booking_hours' => 0,
        'status' => ListingStatus::Published,
    ]);
});

function creditClearedBalance(Operator $operator, float $amount): void
{
    WalletTransaction::create([
        'operator_id' => $operator->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => $amount,
        'fee_amount' => 0,
        'net_amount' => $amount,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Cleared earnings',
    ]);
}

// ── C1: demo desk never moves real money ──────────────────────────────────────

test('demo operator cannot request a payout', function () {
    $this->operator->update(['is_demo' => true]);
    creditClearedBalance($this->operator, 4000000);

    expect(fn () => app(WalletService::class)->createPayoutRequest($this->operator->fresh(), 1000000))
        ->toThrow(ValidationException::class);

    expect(PayoutRequest::count())->toBe(0);
});

test('demo operator cannot change the payout bank account', function () {
    $this->operator->update(['is_demo' => true]);
    $this->actingAs($this->user);

    Livewire::test('pages::settings.payments')
        ->set('bank_account_number', '9999999999')
        ->set('bank_account_name', 'Attacker')
        ->call('updatePaymentSettings')
        ->assertHasErrors(['bank_account_number']);

    expect($this->operator->fresh()->bank_account_number)->toBe('1234567890');
});

test('gateway disbursement refuses demo payouts even if one exists', function () {
    $this->operator->update(['is_demo' => true]);

    $payout = PayoutRequest::create([
        'operator_id' => $this->operator->id,
        'amount' => 100000,
        'bank_provider' => 'BCA',
        'bank_account_name' => 'X',
        'bank_account_number' => '1',
        'status' => PayoutStatus::Pending,
    ]);

    expect(app(DokuPaymentService::class)->disbursePayout($payout)['success'])->toBeFalse();
});

// ── C2: guest cannot choose their own discount ────────────────────────────────

test('guest cannot set the booking discount from the browser', function () {
    expect(fn () => Livewire::test('storefront.booking-box', [
        'bookable' => $this->package,
        'operator' => $this->operator,
    ])->set('discountAmount', 999999999))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('booking total is re-derived from the coupon at submit', function () {
    PlatformCoupon::factory()->forGuest()->create([
        'operator_id' => $this->operator->id,
        'code' => 'TENOFF',
        'discount_type' => 'percentage',
        'discount_value' => 10,
    ]);

    Livewire::test('storefront.booking-box', [
        'bookable' => $this->package,
        'operator' => $this->operator,
    ])
        ->set('requested_date', now()->addDays(3)->format('Y-m-d'))
        ->set('pax_count', 2)
        ->set('couponCode', 'TENOFF')
        ->call('applyCoupon')
        ->set('guest_name', 'Guest')
        ->set('guest_contact', '081234567890')
        ->set('agreed_terms', true)
        ->call('submitBooking');

    $reservation = Reservation::query()->latest()->firstOrFail();

    expect((float) $reservation->terms_snapshot['discount_amount'])->toBe(200000.0)
        ->and((float) $reservation->latestPayment->amount)
        ->toBe(2000000.0 + (float) $reservation->terms_snapshot['service_fee'] - 200000.0);
});

test('booking is refused when the applied coupon stopped being valid', function () {
    $coupon = PlatformCoupon::factory()->forGuest()->create([
        'operator_id' => $this->operator->id,
        'code' => 'GONE',
        'discount_type' => 'fixed',
        'discount_value' => 100000,
    ]);

    $component = Livewire::test('storefront.booking-box', [
        'bookable' => $this->package,
        'operator' => $this->operator,
    ])
        ->set('requested_date', now()->addDays(3)->format('Y-m-d'))
        ->set('couponCode', 'GONE')
        ->call('applyCoupon');

    $coupon->update(['is_active' => false]);

    $component
        ->set('guest_name', 'Guest')
        ->set('guest_contact', '081234567890')
        ->set('agreed_terms', true)
        ->call('submitBooking')
        ->assertHasErrors(['couponCode']);

    expect(Reservation::count())->toBe(0);
});

// ── C3 + H5: plan upgrade discount is server-owned and counted on real payment ─

test('operator cannot set the plan upgrade discount from the browser', function () {
    Plan::seedDefaultPlans();
    $this->actingAs($this->user);

    expect(fn () => Livewire::test('pages::settings.plan')->set('discountAmount', 99999999))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('plan upgrade to a paid plan without a coupon goes to checkout, not free activation', function () {
    Plan::seedDefaultPlans();
    $growth = Plan::where('slug', 'growth')->firstOrFail();
    $this->actingAs($this->user);

    Livewire::test('pages::settings.plan')
        ->call('initiatePlanSwitch', $growth->id)
        ->set('auto_renew_consent', true)
        ->call('confirmPlanSwitch');

    expect($this->operator->fresh()->plan_id)->not->toBe($growth->id)
        ->and(SubscriptionPayment::where('operator_id', $this->operator->id)->value('status'))->toBe('pending');
});

test('subscription coupon usage is counted when DOKU confirms the invoice', function () {
    Plan::seedDefaultPlans();
    $growth = Plan::where('slug', 'growth')->firstOrFail();

    $coupon = PlatformCoupon::factory()->forSubscription()->create([
        'code' => 'HALF',
        'discount_type' => 'percentage',
        'discount_value' => 50,
    ]);

    $payment = app(SubscriptionProrationService::class)->createPendingUpgrade(
        operator: $this->operator,
        targetPlan: $growth,
        couponCode: 'HALF',
        discountAmount: 100000,
    );

    app(DokuPaymentService::class)->processNotification([
        'order' => ['invoice_number' => $payment->invoice_number],
        'transaction' => ['status' => 'SUCCESS'],
    ]);

    // A duplicate notification must not count it twice.
    app(DokuPaymentService::class)->processNotification([
        'order' => ['invoice_number' => $payment->invoice_number],
        'transaction' => ['status' => 'SUCCESS'],
    ]);

    expect($payment->fresh()->status)->toBe('completed')
        ->and($coupon->fresh()->used_count)->toBe(1);
});

// ── C4 + H8: reservation status rules are enforced on the server ──────────────

test('operator cannot complete a future trip to release escrow early', function () {
    $this->actingAs($this->user);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'requested_date' => now()->addDays(10)->format('Y-m-d'),
        'status' => ReservationStatus::Confirmed,
    ]);

    expect(fn () => Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->set('pendingStatusValue', 'completed'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('confirmStatusTransition', 'completed')
        ->call('executeStatusTransition');

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('terminal bookings cannot be moved back', function () {
    $this->actingAs($this->user);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::Cancelled,
    ]);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('confirmStatusTransition', 'confirmed')
        ->assertSet('showConfirmStatusModal', false);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);
});

test('finance teammates cannot change booking status', function () {
    $finance = User::factory()->create();
    $this->operator->users()->attach($finance->id, ['role' => 'finance']);
    $this->actingAs($finance);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::PaymentPending,
    ]);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('confirmStatusTransition', 'cancelled')
        ->assertForbidden();
});

test('sync payment button checks the gateway without crashing', function () {
    $this->actingAs($this->user);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::PaymentPending,
    ]);
    Payment::factory()->create([
        'reservation_id' => $reservation->id,
        'status' => PaymentStatus::Pending,
        'gateway_ref' => 'INV-SYNC-1',
    ]);

    Livewire::test('pages::reservations.show', ['reservation' => $reservation])
        ->call('syncPaymentStatus')
        ->assertDispatched('toast');
});

// ── C5: a late failure webhook never un-pays a booking ────────────────────────

test('late FAILED notification does not reverse a paid booking', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'requested_date' => now()->addDays(5)->format('Y-m-d'),
        'status' => ReservationStatus::PaymentPending,
        'hold_expires_at' => now()->addMinutes(30),
    ]);

    $doku = app(DokuPaymentService::class);
    $session = $doku->createPaymentSession($reservation, 1000000);
    $doku->processNotification([
        'order' => ['invoice_number' => $session['invoice_number']],
        'transaction' => ['status' => 'SUCCESS'],
    ]);

    $doku->processNotification([
        'order' => ['invoice_number' => $session['invoice_number']],
        'transaction' => ['status' => 'FAILED'],
    ]);

    expect($session['payment']->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

// ── C6: payouts are processed exactly once ────────────────────────────────────

test('rejecting the same payout twice restores the money only once', function () {
    config(['doku.simulator_enabled' => false]);
    creditClearedBalance($this->operator, 1000000);

    $wallet = app(WalletService::class);
    $payout = $wallet->createPayoutRequest($this->operator, 500000);

    expect($payout->status)->toBe(PayoutStatus::Pending);

    $wallet->rejectPayout($payout, 'Wrong account name');

    expect(fn () => $wallet->rejectPayout($payout, 'Wrong account name'))
        ->toThrow(ValidationException::class);

    expect($this->operator->getAvailableBalance())->toBe(1000000.0);

    config(['doku.simulator_enabled' => null]);
});

test('a payout cannot be disbursed twice', function () {
    creditClearedBalance($this->operator, 1000000);

    $payout = PayoutRequest::create([
        'operator_id' => $this->operator->id,
        'amount' => 200000,
        'bank_provider' => 'BCA',
        'bank_account_name' => 'Real Owner',
        'bank_account_number' => '1234567890',
        'status' => PayoutStatus::Pending,
    ]);

    $wallet = app(WalletService::class);

    expect($wallet->disbursePayout($payout)['success'])->toBeTrue()
        ->and($wallet->disbursePayout($payout)['success'])->toBeFalse()
        ->and($payout->fresh()->status)->toBe(PayoutStatus::Completed);
});

test('a rejected payout cannot be approved', function () {
    $payout = PayoutRequest::create([
        'operator_id' => $this->operator->id,
        'amount' => 200000,
        'bank_provider' => 'BCA',
        'bank_account_name' => 'Real Owner',
        'bank_account_number' => '1234567890',
        'status' => PayoutStatus::Rejected,
    ]);

    expect(fn () => app(WalletService::class)->approvePayout($payout))
        ->toThrow(ValidationException::class);
});

// ── C7: admin rights only from the flag ───────────────────────────────────────

test('a well-known admin email does not grant admin rights by itself', function () {
    $user = User::factory()->create(['email' => 'admin@travelengine.id', 'is_admin' => false]);

    expect($user->isAdmin())->toBeFalse();
});

// ── H1: stale pay links cannot reopen closed bookings ─────────────────────────

test('pay link does not reopen a cancelled booking', function (ReservationStatus $status) {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'requested_date' => now()->addDays(5)->format('Y-m-d'),
        'status' => $status,
        'hold_expires_at' => null,
    ]);

    $this->get(route('storefront.reservation.pay', $reservation))
        ->assertRedirect(route('storefront.reservation.receipt', $reservation));

    expect($reservation->fresh()->status)->toBe($status)
        ->and(Payment::where('reservation_id', $reservation->id)->count())->toBe(0);
})->with([
    'cancelled' => ReservationStatus::Cancelled,
    'declined' => ReservationStatus::Declined,
    'expired' => ReservationStatus::Expired,
    'completed' => ReservationStatus::Completed,
]);

test('stored media paths on catalog and brand forms cannot be changed from the browser', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create();
    $operator->users()->attach($user->id, ['role' => 'owner']);
    $package = Package::factory()->create(['operator_id' => $operator->id]);

    $this->actingAs($user);

    expect(fn () => Livewire::test('pages::packages.edit', ['package' => $package])->set('existingCoverPhoto', 'operators/other/catalog/x.webp'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect(fn () => Livewire::test('pages::settings.brand')->set('existing_logo_path', 'operators/other/brand/x.webp'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});
