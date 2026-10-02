<?php

use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\AvailabilityBlock;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WalletTransaction;
use App\Services\BookingPricingService;
use App\Services\DokuPaymentService;
use App\Services\PhoneNumber;
use App\Services\PlanLimitService;
use App\Services\ReservationBookingService;
use App\Services\ReservationLifecycleService;
use App\Services\SubscriptionProrationService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();

    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create(['status' => OperatorStatus::Approved]);
    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);

    $this->product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'price' => 500000,
        'capacity_per_day' => 4,
        'status' => ListingStatus::Published,
    ]);
});

function guestDetails(): array
{
    return ['name' => 'Ayu Guest', 'contact' => '081234567890', 'email' => 'ayu@example.com', 'notes' => null];
}

// ── Pricing ──────────────────────────────────────────────────────────────────

test('one pricing service produces subtotal, fee, promo and total', function () {
    PlatformCoupon::factory()->forGuest()->create([
        'operator_id' => $this->operator->id,
        'code' => 'FIFTYK',
        'discount_type' => 'fixed',
        'discount_value' => 50000,
    ]);

    $quote = app(BookingPricingService::class)->quote($this->product, 2, $this->operator, 'fiftyk');

    expect($quote->subtotal)->toBe(1000000.0)
        ->and($quote->serviceFee)->toBe(50000.0)
        ->and($quote->couponCode)->toBe('FIFTYK')
        ->and($quote->discount)->toBe(50000.0)
        ->and($quote->total)->toBe(1000000.0)
        ->and($quote->toSnapshot())->toHaveKeys(['unit_price', 'pax_count', 'subtotal', 'service_fee', 'service_fee_rate', 'coupon_code', 'discount_amount', 'total_price']);
});

test('an unknown promo is reported, not silently priced', function () {
    $quote = app(BookingPricingService::class)->quote($this->product, 1, $this->operator, 'NOPE');

    expect($quote->hasCouponError())->toBeTrue()
        ->and($quote->discount)->toBe(0.0);
});

// ── Booking creation ─────────────────────────────────────────────────────────

test('storefront and pay-link holds are created by the same service with the same snapshot', function () {
    $hold = app(ReservationBookingService::class)->createHold(
        $this->product, $this->operator, now()->addDays(3)->toDateString(), 2, guestDetails(), notifyGuest: false,
    );

    $reservation = $hold['reservation'];

    expect($reservation->status)->toBe(ReservationStatus::PaymentPending)
        ->and($reservation->bookable_type)->toBe('product')
        ->and((float) $reservation->terms_snapshot['total_price'])->toBe(1050000.0)
        ->and((float) $reservation->latestPayment->amount)->toBe(1050000.0)
        ->and($reservation->getQuotedTotal())->toBe(1050000.0)
        ->and($hold['checkout_url'])->toContain('checkout/simulate');
});

test('holds refuse blacked-out dates and draft listings', function () {
    $date = now()->addDays(4)->toDateString();

    AvailabilityBlock::create([
        'operator_id' => $this->operator->id,
        'product_id' => $this->product->id,
        'date_start' => $date,
        'date_end' => $date,
        'reason' => 'Boat maintenance',
    ]);

    expect(fn () => app(ReservationBookingService::class)->createHold($this->product, $this->operator, $date, 1, guestDetails()))
        ->toThrow(ValidationException::class);

    $this->product->update(['status' => ListingStatus::Draft]);

    expect(fn () => app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->addDays(6)->toDateString(), 1, guestDetails()))
        ->toThrow(ValidationException::class);

    expect(Reservation::count())->toBe(0);
});

test('a package is blocked by a blackout on any bundled activity', function () {
    $package = Package::factory()->create(['operator_id' => $this->operator->id, 'status' => ListingStatus::Published]);
    $package->products()->attach($this->product->id, ['quantity_required' => 1]);
    $date = now()->addDays(8)->toDateString();

    AvailabilityBlock::create([
        'operator_id' => $this->operator->id,
        'product_id' => $this->product->id,
        'date_start' => $date,
        'date_end' => $date,
    ]);

    expect($package->isBlackedOutOn($date))->toBeTrue()
        ->and($package->getBlackoutDates())->toHaveKey($date)
        ->and($package->generateTermsSnapshot()['bookable_type'])->toBe('package');
});

// ── Lifecycle ────────────────────────────────────────────────────────────────

test('a late payment on an expired hold is honoured while seats are free', function () {
    $hold = app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->addDays(3)->toDateString(), 1, guestDetails(), notifyGuest: false);
    $reservation = $hold['reservation'];
    $reservation->update(['status' => ReservationStatus::Expired, 'hold_expires_at' => now()->subMinute()]);

    app(DokuPaymentService::class)->processNotification([
        'order' => ['invoice_number' => $reservation->latestPayment->gateway_ref],
        'transaction' => ['status' => 'SUCCESS'],
    ]);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('a late payment for seats that were resold is refunded, not overbooked', function () {
    $date = now()->addDays(3)->toDateString();
    $late = app(ReservationBookingService::class)->createHold($this->product, $this->operator, $date, 2, guestDetails(), notifyGuest: false)['reservation'];
    $late->update(['status' => ReservationStatus::Expired, 'hold_expires_at' => now()->subMinute()]);

    app(ReservationBookingService::class)->createHold($this->product, $this->operator, $date, 4, guestDetails(), notifyGuest: false);

    app(DokuPaymentService::class)->processNotification([
        'order' => ['invoice_number' => $late->latestPayment->gateway_ref],
        'transaction' => ['status' => 'SUCCESS'],
    ]);

    expect($late->fresh()->status)->toBe(ReservationStatus::Expired)
        ->and($late->latestPayment()->first()->status)->toBe(PaymentStatus::Refunded)
        ->and(WalletTransaction::where('reservation_id', $late->id)->count())->toBe(0);
});

test('a replayed success after a refund does not re-open the payment', function () {
    $reservation = app(ReservationBookingService::class)->createHold($this->product, $this->operator, now()->addDays(3)->toDateString(), 1, guestDetails(), notifyGuest: false)['reservation'];
    $invoice = $reservation->latestPayment->gateway_ref;
    $doku = app(DokuPaymentService::class);

    $doku->processNotification(['order' => ['invoice_number' => $invoice], 'transaction' => ['status' => 'SUCCESS']]);
    app(ReservationLifecycleService::class)->cancelByGuest($reservation->fresh());
    $doku->processNotification(['order' => ['invoice_number' => $invoice], 'transaction' => ['status' => 'SUCCESS']]);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->latestPayment()->first()->status)->toBe(PaymentStatus::Refunded);
});

test('two cancellations at once cannot both run', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'product',
        'bookable_id' => $this->product->id,
        'status' => ReservationStatus::Confirmed,
    ]);

    $lock = Cache::lock('reservation-cancel:'.$reservation->id, 60);
    $lock->get();

    expect(fn () => app(ReservationLifecycleService::class)->cancel($reservation, 'test'))
        ->toThrow(ValidationException::class);

    $lock->release();
});

test('expiring holds never touches a booking that is already paid', function () {
    $confirmed = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'status' => ReservationStatus::Confirmed,
        'hold_expires_at' => now()->subHour(),
    ]);
    $stale = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'status' => ReservationStatus::PaymentPending,
        'hold_expires_at' => now()->subHour(),
    ]);

    $codes = app(ReservationLifecycleService::class)->expireStaleHolds();

    expect($codes)->toBe([$stale->code])
        ->and($confirmed->fresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($stale->fresh()->status)->toBe(ReservationStatus::Expired);
});

// ── Wallet ───────────────────────────────────────────────────────────────────

test('reversing a cleared earning twice debits only once', function () {
    $reservation = Reservation::factory()->create(['operator_id' => $this->operator->id]);

    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $reservation->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 800000,
        'fee_amount' => 0,
        'net_amount' => 800000,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Earning',
    ]);

    $wallet = app(WalletService::class);
    $wallet->cancelBookingEarning($reservation, 'first');
    $wallet->cancelBookingEarning($reservation, 'second');

    expect($this->operator->getAvailableBalance())->toBe(0.0);
});

test('card disputes hold the same amount from the webhook and from the admin desk', function () {
    $reservation = Reservation::factory()->create(['operator_id' => $this->operator->id, 'status' => ReservationStatus::Confirmed]);
    Payment::factory()->create(['reservation_id' => $reservation->id, 'amount' => 1000000, 'status' => PaymentStatus::Paid]);

    $hold = app(WalletService::class)->openCardDispute($reservation);

    expect((float) $hold->net_amount)->toBe(-1 * (1000000 + WalletService::DISPUTE_ADMIN_FEE))
        ->and(app(WalletService::class)->openCardDispute($reservation))->toBeNull();
});

// ── Plans ────────────────────────────────────────────────────────────────────

test('a mid-cycle prorated upgrade keeps the current billing period end', function () {
    Plan::seedDefaultPlans();
    $growth = Plan::where('slug', 'growth')->firstOrFail();
    $agency = Plan::where('slug', 'agency')->firstOrFail();
    $periodEnd = now()->addDays(15)->startOfSecond();

    $this->operator->update([
        'plan_id' => $growth->id,
        'subscription_interval' => 'monthly',
        'subscribed_at' => now()->subDays(15),
        'plan_expires_at' => $periodEnd,
    ]);

    $payment = app(SubscriptionProrationService::class)->createPendingUpgrade($this->operator->fresh(), $agency);
    app(SubscriptionProrationService::class)->completePendingPayment($payment, 'TEST', 'doku');

    $operator = $this->operator->fresh();

    expect($operator->plan_id)->toBe($agency->id)
        ->and($operator->plan_expires_at->timestamp)->toBe($periodEnd->timestamp);
});

test('a paid subscription promo is logged so first-purchase-only codes cannot be reused', function () {
    Plan::seedDefaultPlans();
    $growth = Plan::where('slug', 'growth')->firstOrFail();
    $coupon = PlatformCoupon::factory()->firstPurchaseOnly()->create([
        'scope' => 'subscription',
        'code' => 'WELCOME10',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'min_spend' => 0,
        'max_uses' => null,
        'operator_id' => null,
    ]);

    expect($coupon->validateFor(500000, $this->operator->id)['valid'])->toBeTrue();

    $service = app(SubscriptionProrationService::class);
    $payment = $service->createPendingUpgrade($this->operator->fresh(), $growth, couponCode: 'WELCOME10', discountAmount: 1000);
    $service->completePendingPayment($payment, 'TEST', 'doku');
    $service->completePendingPayment($payment, 'TEST', 'doku');

    $coupon->refresh();

    expect($coupon->used_count)->toBe(1)
        ->and($coupon->redemptions()->where('operator_id', $this->operator->id)->count())->toBe(1)
        ->and($coupon->validateFor(500000, $this->operator->id)['valid'])->toBeFalse()
        ->and($coupon->validateFor(500000, Operator::factory()->create()->id)['valid'])->toBeTrue();
});

test('downgrading drafts listings above the new cap and blocks re-publishing them', function () {
    Plan::seedDefaultPlans();
    $starter = Plan::where('slug', 'starter')->firstOrFail();
    $growth = Plan::where('slug', 'growth')->firstOrFail();
    $this->operator->update(['plan_id' => $growth->id, 'plan_expires_at' => now()->addDays(10)]);

    Product::factory()->count(6)->create(['operator_id' => $this->operator->id, 'status' => ListingStatus::Published]);

    app(SubscriptionProrationService::class)->executeImmediateDowngrade($this->operator->fresh(), $starter);

    $operator = $this->operator->fresh();
    $live = $operator->products()->where('status', ListingStatus::Published)->count();
    $drafted = $operator->products()->where('status', ListingStatus::Draft)->first();

    expect($live)->toBe($starter->package_limit)
        ->and(app(PlanLimitService::class)->canPublish($operator, $drafted))->toBeFalse();
});

// ── Permissions & tenancy ────────────────────────────────────────────────────

test('a bookings teammate cannot change the catalog', function () {
    $helper = User::factory()->create();
    $this->operator->users()->attach($helper->id, ['role' => 'reservation']);
    $this->actingAs($helper);

    Livewire::test('pages::products.index')
        ->call('deleteProduct', $this->product->id)
        ->assertForbidden();

    expect(Product::whereKey($this->product->id)->exists())->toBeTrue();
});

test('an activity cannot be linked to another operator\'s vendor', function () {
    $foreignVendor = Vendor::factory()->create(['operator_id' => Operator::factory()->create()->id]);
    $this->actingAs($this->user);

    Livewire::test('pages::products.edit', ['product' => $this->product])
        ->set('vendor_id', $foreignVendor->id)
        ->call('save')
        ->assertHasErrors(['vendor_id']);
});

// ── Small shared rules ───────────────────────────────────────────────────────

test('phone numbers are normalised the same way everywhere', function () {
    expect(PhoneNumber::normalize('0812-3456 789'))->toBe('628123456789')
        ->and(PhoneNumber::normalize('+62 812 3456 789'))->toBe('628123456789')
        ->and(PhoneNumber::whatsAppLink(''))->toBeNull();
});

test('llms.txt plan lines come from the live plans', function () {
    Plan::seedDefaultPlans();
    $growth = Plan::where('slug', 'growth')->firstOrFail();

    $this->get('/llms.txt')
        ->assertOk()
        ->assertSee($growth->listingLimitLabel());
});
