<?php

namespace App\Services;

use App\Contracts\Bookable;
use App\Models\Operator;
use App\Models\PlatformCoupon;
use App\Models\PlatformSetting;

/**
 * The single place where a booking's subtotal, guest service fee, promo discount and total
 * are worked out. Storefront checkout, operator booking links and receipts all use it.
 */
class BookingPricingService
{
    public function quote(Bookable $bookable, int $pax, Operator $operator, ?string $couponCode = null): BookingQuote
    {
        $platform = PlatformSetting::current();

        $pax = max(1, $pax);
        $unitPrice = $bookable->getPrice();
        $subtotal = $unitPrice * $pax;
        $serviceFeeRate = $platform->getGuestServiceFeeRate();
        $serviceFee = $platform->calculateGuestServiceFee($subtotal, $operator);

        $appliedCode = null;
        $discount = 0.0;
        $couponError = null;

        if ($couponCode !== null && trim($couponCode) !== '') {
            $coupon = PlatformCoupon::findForGuest($couponCode, $operator);
            $result = $coupon?->validateFor($subtotal, $operator->id)
                ?? ['valid' => false, 'reason' => __('Invalid promo code.')];

            $offered = (float) ($result['discount'] ?? 0);

            if ($coupon !== null && $result['valid'] && $offered > 0) {
                $appliedCode = $coupon->code;
                $discount = round($offered);
            } else {
                $couponError = (string) ($result['reason'] ?? __('Promo code cannot be applied.'));
            }
        }

        return new BookingQuote(
            unitPrice: $unitPrice,
            pax: $pax,
            subtotal: $subtotal,
            serviceFeeRate: $serviceFeeRate,
            serviceFee: $serviceFee,
            couponCode: $appliedCode,
            discount: $discount,
            total: max(0.0, round($subtotal + $serviceFee - $discount)),
            couponError: $couponError,
        );
    }
}
