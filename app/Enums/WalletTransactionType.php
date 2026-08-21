<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case BookingEarning = 'booking_earning';
    case PlatformCommission = 'platform_commission';
    case PayoutWithdrawal = 'payout_withdrawal';
    case RefundDeduction = 'refund_deduction';
    case ManualAdjustment = 'manual_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::BookingEarning => 'Booking Earning',
            self::PlatformCommission => 'Platform Commission',
            self::PayoutWithdrawal => 'Payout Withdrawal',
            self::RefundDeduction => 'Refund Deduction',
            self::ManualAdjustment => 'Manual Adjustment',
        };
    }
}
