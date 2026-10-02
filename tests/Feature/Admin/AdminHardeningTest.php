<?php

use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Http\Middleware\EnsureOnPlatformDomain;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\AdminAuditLog;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\PlatformSetting;
use App\Models\Reservation;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AdminMetricsService;
use App\Services\DokuPaymentService;
use App\Services\DomainResolverService;
use App\Services\OperatorAccountService;
use App\Services\ReservationLifecycleService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

beforeEach(function () {
    Cache::flush();
    Plan::seedDefaultPlans();

    $this->admin = User::factory()->admin()->create(['email' => 'chief@travelengine.id']);
    $this->operator = Operator::factory()->create(['status' => OperatorStatus::Approved]);
});

function creditOperator(Operator $operator, float $amount): void
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

// ── AD1 / AD13: admin sign-in ────────────────────────────────────────────────

test('an admin with two-factor enabled must pass the challenge before being signed in', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create(['email' => 'secure@travelengine.id']);

    Livewire::test('pages::admin.login')
        ->set('email', 'secure@travelengine.id')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
    expect(session('login.id'))->toBe($admin->id);
});

test('an admin without two-factor signs in and the sign-in is logged', function () {
    Livewire::test('pages::admin.login')
        ->set('email', 'chief@travelengine.id')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($this->admin);
    expect(AdminAuditLog::where('action', 'admin.login')->count())->toBe(1);
});

test('the admin form never confirms an operator password', function () {
    User::factory()->create(['email' => 'owner@shop.test']);

    Livewire::test('pages::admin.login')
        ->set('email', 'owner@shop.test')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email' => __('These credentials do not match our platform records.')]);

    $this->assertGuest();
});

// ── AD2: admin checks run on every Livewire action ───────────────────────────

test('admin and platform middleware are re-applied on Livewire actions', function () {
    $persistent = app(PersistentMiddleware::class)->getPersistentMiddleware();

    expect($persistent)->toContain(EnsureUserIsAdmin::class)
        ->and($persistent)->toContain(EnsureOnPlatformDomain::class);
});

// ── AD4: managing an operator ────────────────────────────────────────────────

test('an admin who is not managing a shop has no operator desk', function () {
    $this->actingAs($this->admin);

    expect($this->admin->currentOperator())->toBeNull();
});

test('managing a shop is logged, expires, and can be stopped', function () {
    $this->actingAs($this->admin);
    $accounts = app(OperatorAccountService::class);

    $accounts->startManaging($this->operator);
    expect($this->admin->currentOperator()?->id)->toBe($this->operator->id);

    session([User::IMPERSONATION_STARTED_KEY => now()->subMinutes(User::IMPERSONATION_TTL_MINUTES + 1)->getTimestamp()]);
    expect($this->admin->currentOperator())->toBeNull();

    $accounts->startManaging($this->operator);
    $this->post(route('admin.operators.stop-managing'))->assertRedirect(route('admin.operators.index'));

    expect(session()->has(User::IMPERSONATION_SESSION_KEY))->toBeFalse()
        ->and(AdminAuditLog::where('action', 'operator.manage_started')->count())->toBe(2)
        ->and(AdminAuditLog::where('action', 'operator.manage_stopped')->count())->toBe(1);
});

test('a managing admin cannot request payouts or change the payout bank', function () {
    creditOperator($this->operator, 2000000);
    $this->actingAs($this->admin);
    app(OperatorAccountService::class)->startManaging($this->operator);

    expect(fn () => app(WalletService::class)->createPayoutRequest($this->operator, 500000))
        ->toThrow(ValidationException::class);

    Livewire::test('pages::settings.payments')
        ->set('bank_account_number', '999')
        ->call('updatePaymentSettings')
        ->assertHasErrors(['bank_account_number']);
});

// ── AD3: audit log ───────────────────────────────────────────────────────────

test('admin status changes are logged and log rows cannot be edited', function () {
    $this->actingAs($this->admin);

    app(OperatorAccountService::class)->changeStatus($this->operator, OperatorStatus::Suspended);

    $entry = AdminAuditLog::where('action', 'operator.status_changed')->firstOrFail();

    expect($entry->operator_id)->toBe($this->operator->id)
        ->and($entry->actor_email)->toBe('chief@travelengine.id')
        ->and($entry->context)->toBe(['from' => 'Approved', 'to' => 'Suspended']);

    expect(fn () => $entry->update(['action' => 'x']))->toThrow(LogicException::class);
});

test('the audit log page lists admin actions', function () {
    $this->actingAs($this->admin);
    app(OperatorAccountService::class)->changeStatus($this->operator, OperatorStatus::Suspended);

    $this->get(route('admin.audit-log.index'))
        ->assertOk()
        ->assertSee('operator.status_changed');
});

// ── AD6: promo codes per owner ───────────────────────────────────────────────

test('two operators can use the same storefront promo code', function () {
    $other = Operator::factory()->create();

    PlatformCoupon::factory()->forGuest()->create(['operator_id' => $this->operator->id, 'code' => 'BALI10']);
    $second = PlatformCoupon::factory()->forGuest()->create(['operator_id' => $other->id, 'code' => 'BALI10']);

    expect($second->exists)->toBeTrue();
});

// ── AD7: extending a subscription ────────────────────────────────────────────

test('extending a subscription is bounded and leaves an invoice', function () {
    $growth = Plan::where('slug', 'growth')->firstOrFail();
    $this->operator->update(['plan_id' => $growth->id, 'plan_expires_at' => now()->addDays(5)]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.plans')
        ->call('extendSubscription', $this->operator->id, -30);

    expect($this->operator->fresh()->plan_expires_at->isAfter(now()->addDays(4)))->toBeTrue()
        ->and(SubscriptionPayment::where('gateway', 'admin_extension')->count())->toBe(0);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.plans')
        ->call('extendSubscription', $this->operator->id, 30);

    expect((int) now()->diffInDays($this->operator->fresh()->plan_expires_at))->toBeGreaterThanOrEqual(34)
        ->and(SubscriptionPayment::where('gateway', 'admin_extension')->count())->toBe(1)
        ->and(AdminAuditLog::where('action', 'subscription.extended')->count())->toBe(1);
});

// ── AD8–AD10: admin figures ──────────────────────────────────────────────────

test('MRR counts only paying operators with a live period', function () {
    $growth = Plan::where('slug', 'growth')->firstOrFail();

    $paying = Operator::factory()->create(['status' => OperatorStatus::Approved, 'plan_id' => $growth->id, 'plan_expires_at' => now()->addDays(10), 'subscription_interval' => 'monthly']);
    SubscriptionPayment::create(['operator_id' => $paying->id, 'plan_id' => $growth->id, 'invoice_number' => 'SUB-P', 'billing_interval' => 'monthly', 'gross_amount' => 299000, 'net_amount_paid' => 299000, 'status' => 'completed', 'gateway' => 'doku']);

    $comped = Operator::factory()->create(['status' => OperatorStatus::Approved, 'plan_id' => $growth->id, 'plan_expires_at' => now()->addDays(10)]);
    SubscriptionPayment::create(['operator_id' => $comped->id, 'plan_id' => $growth->id, 'invoice_number' => 'SUB-C', 'billing_interval' => 'monthly', 'gross_amount' => 0, 'net_amount_paid' => 0, 'status' => 'completed', 'gateway' => 'admin_complimentary']);

    Operator::factory()->create(['status' => OperatorStatus::Approved, 'plan_id' => $growth->id, 'plan_expires_at' => now()->subDays(2)]);

    expect(app(AdminMetricsService::class)->monthlyRecurringRevenue())->toBe((float) $growth->price_monthly);
});

test('coupon usage counts only paid invoices', function () {
    $growth = Plan::where('slug', 'growth')->firstOrFail();
    $coupon = PlatformCoupon::factory()->forSubscription()->create(['code' => 'HALF']);

    foreach (['completed' => 150000, 'pending' => 150000, 'failed' => 150000] as $status => $net) {
        SubscriptionPayment::create(['operator_id' => $this->operator->id, 'plan_id' => $growth->id, 'invoice_number' => 'SUB-'.$status, 'billing_interval' => 'monthly', 'gross_amount' => 300000, 'net_amount_paid' => $net, 'status' => $status, 'gateway' => 'doku', 'breakdown' => ['coupon_code' => 'HALF', 'discount_amount' => 150000]]);
    }

    expect(app(AdminMetricsService::class)->subscriptionCouponUsage($coupon))->toBe(['uses' => 1, 'discount' => 150000.0, 'revenue' => 150000.0]);
});

test('admin figures include guest-fee income, escrow and held disputes', function () {
    $reservation = Reservation::factory()->create(['operator_id' => $this->operator->id]);

    WalletTransaction::create(['operator_id' => $this->operator->id, 'reservation_id' => $reservation->id, 'type' => WalletTransactionType::PlatformCommission, 'gross_amount' => 0, 'fee_amount' => 50000, 'net_amount' => 0, 'status' => WalletTransactionStatus::PendingEscrow, 'description' => 'fee']);
    WalletTransaction::create(['operator_id' => $this->operator->id, 'reservation_id' => $reservation->id, 'type' => WalletTransactionType::BookingEarning, 'gross_amount' => 1000000, 'fee_amount' => 0, 'net_amount' => 1000000, 'status' => WalletTransactionStatus::PendingEscrow, 'description' => 'earning']);

    $metrics = app(AdminMetricsService::class);

    expect($metrics->guestFeeRevenue())->toBe(50000.0)
        ->and($metrics->escrowLiability())->toBe(1000000.0)
        ->and($metrics->openDisputeHolds())->toBe(0.0);
});

// ── AD11: settings ───────────────────────────────────────────────────────────

test('platform settings refuse other currencies and non-Slack webhooks and save the fee cap', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.platform')
        ->set('currency_code', 'USD')
        ->set('slack_webhook_url', 'http://169.254.169.254/latest')
        ->call('updatePlatformSettings')
        ->assertHasErrors(['currency_code', 'slack_webhook_url']);

    Livewire::test('pages::admin.platform')
        ->set('guest_service_fee_cap', 300000)
        ->call('updatePlatformSettings')
        ->assertHasNoErrors();

    expect(PlatformSetting::current()->fresh()->getGuestServiceFeeCap())->toBe(300000.0)
        ->and(AdminAuditLog::where('action', 'platform.settings_updated')->exists())->toBeTrue();
});

test('payments without a price snapshot keep 100% for the operator', function () {
    $reservation = Reservation::factory()->create(['operator_id' => $this->operator->id, 'terms_snapshot' => []]);

    $session = app(DokuPaymentService::class)->createPaymentSession($reservation, 1000000);

    expect((float) $session['payment']->split_details['platform_commission'])->toBe(0.0)
        ->and((float) $session['payment']->split_details['operator_amount'])->toBe(1000000.0);
});

test('local hosts are not treated as the platform on production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(app(DomainResolverService::class)->isPlatformRoot('localhost'))->toBeFalse();

    app()->detectEnvironment(fn () => 'testing');
});

// ── AD12: finance tools ──────────────────────────────────────────────────────

test('a lost card dispute keeps the deduction and cancels the booking', function () {
    creditOperator($this->operator, 2000000);
    $reservation = Reservation::factory()->create(['operator_id' => $this->operator->id, 'status' => ReservationStatus::Confirmed]);
    Payment::factory()->create(['reservation_id' => $reservation->id, 'amount' => 1000000, 'status' => PaymentStatus::Paid]);

    app(WalletService::class)->openCardDispute($reservation);
    $afterHold = $this->operator->getAvailableBalance();

    app(ReservationLifecycleService::class)->resolveCardDispute($reservation, won: false);

    expect($this->operator->getAvailableBalance())->toBe($afterHold)
        ->and(app(WalletService::class)->outstandingDisputeHold($reservation))->toBe(0.0)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->latestPayment()->first()->refund_status)->toBe('chargeback');
});

test('admins can cancel a booking and adjust a wallet with a reason, both logged', function () {
    $reservation = Reservation::factory()->create(['operator_id' => $this->operator->id, 'status' => ReservationStatus::Confirmed]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.operators.show', ['operator' => $this->operator])
        ->call('cancelBooking', $reservation->id)
        ->set('adjustmentAmount', '-25000')
        ->set('adjustmentReason', 'x')
        ->call('adjustWallet')
        ->assertHasErrors(['adjustmentReason'])
        ->set('adjustmentReason', 'Refund of duplicate payout fee')
        ->call('adjustWallet')
        ->assertHasNoErrors();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($this->operator->getAvailableBalance())->toBe(-25000.0)
        ->and(AdminAuditLog::whereIn('action', ['reservation.cancelled_by_admin', 'wallet.adjusted'])->count())->toBe(2);
});
