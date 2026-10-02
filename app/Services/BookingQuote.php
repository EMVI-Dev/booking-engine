<?php

namespace App\Services;

/**
 * Immutable price breakdown for one booking, produced only by BookingPricingService.
 */
final readonly class BookingQuote
{
    public function __construct(
        public float $unitPrice,
        public int $pax,
        public float $subtotal,
        public float $serviceFeeRate,
        public float $serviceFee,
        public ?string $couponCode,
        public float $discount,
        public float $total,
        public ?string $couponError = null,
    ) {}

    public function hasCouponError(): bool
    {
        return $this->couponError !== null;
    }

    /**
     * Pricing keys frozen into reservations.terms_snapshot (read back by payments, receipts and reports).
     *
     * @return array{unit_price: float, pax_count: int, subtotal: float, service_fee: float, service_fee_rate: float, coupon_code: ?string, discount_amount: float, total_price: float}
     */
    public function toSnapshot(): array
    {
        return [
            'unit_price' => $this->unitPrice,
            'pax_count' => $this->pax,
            'subtotal' => $this->subtotal,
            'service_fee' => $this->serviceFee,
            'service_fee_rate' => $this->serviceFeeRate,
            'coupon_code' => $this->couponCode,
            'discount_amount' => $this->discount,
            'total_price' => $this->total,
        ];
    }
}
