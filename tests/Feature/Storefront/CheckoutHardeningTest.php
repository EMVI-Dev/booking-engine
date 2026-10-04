<?php

use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\PlatformCoupon;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\WalletTransaction;
use App\Services\BookingPricingService;
use App\Services\CapacityService;
use App\Services\DokuPaymentService;
use App\Services\ReservationBookingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Cache::flush();

    $this->operator = Operator::factory()->create(['status' => OperatorStatus::Approved]);
    $this->product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'price' => 500000,
        'capacity_per_day' => 2,
        'advance_booking_hours' => 0,
        'status' => ListingStatus::Published,
    ]);
});

function checkoutGuest(): array
{
    return ['name' => 'Ayu Guest', 'contact' => '081234567890', 'email' => 'ayu@example.com', 'notes' => null];
}

function payInvoice(Payment $payment): void
{
    app(DokuPaymentService::class)->processNotification([
        'order' => ['invoice_number' => $payment->gateway_ref, 'amount' => (float) $payment->amount],
        'transaction' => ['status' => 'SUCCESS'],
    ], requireAmount: true);
}

test('a second payment on an already paid booking is refunded, not credited twice', function () {
    $hold = app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->addDays(3)->toDateString(), 1, checkoutGuest(), notifyGuest: false);
    $reservation = $hold['reservation'];
    $first = $reservation->latestPayment;
    payInvoice($first);
    $walletRows = WalletTransaction::where('reservation_id', $reservation->id)->count();

    $second = app(DokuPaymentService::class)->createPaymentSession($reservation->fresh(), (float) $first->amount)['payment'];
    payInvoice($second);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($first->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($second->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(WalletTransaction::where('reservation_id', $reservation->id)->count())->toBe($walletRows);
});

test('a late payment on a run-out hold is refunded when the seats were sold meanwhile', function () {
    $date = now()->addDays(3)->toDateString();
    $late = app(ReservationBookingService::class)->createHold($this->product, $this->operator, $date, 2, checkoutGuest(), notifyGuest: false)['reservation'];
    $late->update(['hold_expires_at' => now()->subMinute()]);

    // Seats freed by the run-out hold are sold to someone else before the expiry job runs.
    $other = app(ReservationBookingService::class)->createHold($this->product, $this->operator, $date, 2, checkoutGuest(), notifyGuest: false)['reservation'];
    payInvoice($other->latestPayment);

    payInvoice($late->latestPayment);

    expect($late->latestPayment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($late->fresh()->status)->not->toBe(ReservationStatus::Confirmed);
});

test('a late payment on a run-out hold is honoured while seats are still free', function () {
    $hold = app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->addDays(3)->toDateString(), 1, checkoutGuest(), notifyGuest: false)['reservation'];
    $hold->update(['hold_expires_at' => now()->subMinute()]);

    payInvoice($hold->latestPayment);

    expect($hold->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('when the payment page cannot be opened the hold is released and the guest sees a friendly error', function () {
    $this->mock(DokuPaymentService::class)
        ->shouldReceive('createPaymentSession')
        ->andThrow(new RuntimeException('DOKU is down'));

    try {
        app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->addDays(3)->toDateString(), 2, checkoutGuest(), notifyGuest: false);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('payment');
    }

    expect(Reservation::query()->sole()->status)->toBe(ReservationStatus::Expired)
        ->and(app(CapacityService::class)->remainingCapacity($this->product, now()->addDays(3)->toDateString()))->toBe(2);
});

test('a guest coupon is counted when the booking is paid, not when the hold is made', function () {
    $coupon = PlatformCoupon::factory()->forGuest()->create([
        'operator_id' => $this->operator->id,
        'code' => 'TENK',
        'discount_type' => 'fixed',
        'discount_value' => 10000,
    ]);

    $hold = app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->addDays(3)->toDateString(), 1, checkoutGuest(), couponCode: 'TENK', notifyGuest: false);

    expect($coupon->fresh()->used_count)->toBe(0);

    payInvoice($hold['reservation']->latestPayment);

    expect($coupon->fresh()->used_count)->toBe(1);
});

test('the advance-booking rule is enforced by the booking service itself', function () {
    $this->product->update(['advance_booking_hours' => 48]);

    expect(fn () => app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->addDay()->toDateString(), 1, checkoutGuest()))
        ->toThrow(ValidationException::class);

    $hold = app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->addDays(3)->toDateString(), 1, checkoutGuest(), notifyGuest: false);
    expect($hold['reservation']->exists)->toBeTrue();
});

test('operators may still book last minute from the desk', function () {
    $this->product->update(['advance_booking_hours' => 48]);

    $hold = app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->toDateString(), 1, checkoutGuest(), notifyGuest: false, enforceAdvanceBooking: false);

    expect($hold['reservation']->exists)->toBeTrue();

    expect(fn () => app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->subDay()->toDateString(), 1, checkoutGuest(), enforceAdvanceBooking: false))
        ->toThrow(ValidationException::class);
});

test('a signed success webhook without an amount is refused', function () {
    $hold = app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->addDays(3)->toDateString(), 1, checkoutGuest(), notifyGuest: false);

    $processed = app(DokuPaymentService::class)->processNotification([
        'order' => ['invoice_number' => $hold['reservation']->latestPayment->gateway_ref],
        'transaction' => ['status' => 'SUCCESS'],
    ], requireAmount: true);

    expect($processed)->toBeFalse()
        ->and($hold['reservation']->fresh()->status)->toBe(ReservationStatus::PaymentPending);
});

test('suspended or pending shops cannot open a checkout', function (OperatorStatus $status) {
    $this->operator->update(['status' => $status]);

    expect(fn () => app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->addDays(3)->toDateString(), 1, checkoutGuest()))
        ->toThrow(ValidationException::class);
})->with([OperatorStatus::Suspended, OperatorStatus::Pending]);

test('fees and totals are whole rupiah', function () {
    $this->product->update(['price' => 333333]);

    $quote = app(BookingPricingService::class)->quote($this->product->fresh(), 1, $this->operator);

    expect($quote->serviceFee)->toBe(round($quote->serviceFee))
        ->and($quote->total)->toBe(round($quote->total));
});
