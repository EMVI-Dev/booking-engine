<?php

namespace App\Services;

use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\PlatformCoupon;
use App\Models\SubscriptionPayment;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Every figure shown on the platform admin desk, calculated in one place.
 */
class AdminMetricsService
{
    /**
     * Invoice gateways that are not money from the operator (grants, extensions, 100% promos).
     *
     * @var list<string>
     */
    public const NON_PAYING_GATEWAYS = ['admin_complimentary', 'admin_extension', 'promo_code', 'free'];

    /**
     * @return array{count: int, amount: float}
     */
    public function payoutTotals(PayoutStatus $status): array
    {
        $row = PayoutRequest::query()
            ->where('status', $status)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount')
            ->first();

        return [
            'count' => (int) ($row->total_count ?? 0),
            'amount' => (float) ($row->total_amount ?? 0),
        ];
    }

    /**
     * Subscription money actually collected (completed invoices).
     */
    public function subscriptionRevenue(?CarbonInterface $from = null, ?CarbonInterface $to = null): float
    {
        return (float) SubscriptionPayment::query()
            ->where('status', SubscriptionPayment::STATUS_COMPLETED)
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->sum('net_amount_paid');
    }

    /**
     * Completed subscription revenue grouped by month (Y-m => total).
     *
     * @return array<string, float>
     */
    public function subscriptionRevenueByMonth(CarbonInterface $from, CarbonInterface $to): array
    {
        $monthExpression = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        return SubscriptionPayment::query()
            ->where('status', SubscriptionPayment::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->groupByRaw($monthExpression)
            ->selectRaw("{$monthExpression} as month_key, SUM(net_amount_paid) as total")
            ->pluck('total', 'month_key')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }

    /**
     * Monthly recurring revenue from operators who are actually paying today.
     *
     * Counts approved operators on a paid plan whose period has not ended and whose plan
     * was last bought with real money (not an admin grant, extension or 100% promo).
     */
    public function monthlyRecurringRevenue(): float
    {
        $operators = Operator::query()
            ->where('status', OperatorStatus::Approved)
            ->whereNotNull('plan_id')
            ->where('plan_expires_at', '>', now())
            ->with('plan')
            ->get();

        $lastPlanInvoiceGateway = SubscriptionPayment::query()
            ->whereIn('operator_id', $operators->pluck('id'))
            ->where('status', SubscriptionPayment::STATUS_COMPLETED)
            ->where('gateway', '!=', 'admin_extension')
            ->orderBy('created_at')
            ->get(['operator_id', 'gateway', 'created_at'])
            ->keyBy('operator_id')
            ->map(fn (SubscriptionPayment $payment): string => (string) $payment->gateway);

        return (float) $operators->sum(function (Operator $operator) use ($lastPlanInvoiceGateway): float {
            $plan = $operator->plan;

            if ($plan === null || $plan->isFree()) {
                return 0.0;
            }

            if (in_array($lastPlanInvoiceGateway->get($operator->id), self::NON_PAYING_GATEWAYS, true)) {
                return 0.0;
            }

            return $operator->subscription_interval === 'yearly'
                ? (float) $plan->price_yearly / 12
                : (float) $plan->price_monthly;
        });
    }

    /**
     * Platform income from the guest service fee on bookings that were not cancelled.
     */
    public function guestFeeRevenue(): float
    {
        return (float) WalletTransaction::query()
            ->where('type', WalletTransactionType::PlatformCommission)
            ->where('status', '!=', WalletTransactionStatus::Cancelled)
            ->sum('fee_amount');
    }

    /**
     * Total value of paid guest checkouts (incl. service fee).
     */
    public function grossBookingValue(): float
    {
        return (float) Payment::query()->where('status', PaymentStatus::Paid)->sum('amount');
    }

    /**
     * Operator earnings still held until trip day.
     */
    public function escrowLiability(): float
    {
        return (float) WalletTransaction::query()
            ->where('status', WalletTransactionStatus::PendingEscrow)
            ->sum('net_amount');
    }

    /**
     * Cleared operator balances not yet paid out (what the platform owes operators today).
     */
    public function clearedOperatorBalances(): float
    {
        return (float) WalletTransaction::query()
            ->where('status', WalletTransactionStatus::Cleared)
            ->sum('net_amount');
    }

    /**
     * Money currently held on open card disputes.
     */
    public function openDisputeHolds(): float
    {
        $net = (float) WalletTransaction::query()
            ->whereIn('type', [WalletTransactionType::DisputeHold, WalletTransactionType::DisputeRelease])
            ->sum('net_amount');

        return max(0.0, -1 * $net);
    }

    /**
     * How a subscription promo has been used: only paid (completed) invoices count.
     *
     * @return array{uses: int, discount: float, revenue: float}
     */
    public function subscriptionCouponUsage(PlatformCoupon $coupon): array
    {
        $paid = SubscriptionPayment::query()
            ->where('status', SubscriptionPayment::STATUS_COMPLETED)
            ->where('breakdown->coupon_code', $coupon->code)
            ->get(['breakdown', 'net_amount_paid']);

        return [
            'uses' => $paid->count(),
            'discount' => (float) $paid->sum(fn (SubscriptionPayment $payment): float => (float) data_get($payment->breakdown, 'discount_amount', 0)),
            'revenue' => (float) $paid->sum('net_amount_paid'),
        ];
    }
}
