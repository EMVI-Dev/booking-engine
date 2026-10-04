<?php

namespace App\Contracts;

use App\Models\Operator;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface Bookable
{
    public function getId(): string;

    public function getTitle(): string;

    public function getOperator(): Operator;

    public function getOperatorId(): string;

    public function getPrice(): float;

    public function getFreeCancellationHours(): int;

    public function getAdvanceBookingHours(): int;

    /**
     * The first trip date a guest may book (advance-booking hours from now, as a date).
     */
    public function earliestBookableDate(?CarbonInterface $fromTime = null): CarbonInterface;

    /**
     * @return array<int, string>
     */
    public function getInclusions(): array;

    /**
     * @return array<int, string>
     */
    public function getExclusions(): array;

    public function getTermsAndConditions(): ?string;

    /**
     * Get the required products and their quantities for this bookable item.
     * For a Product, returns a collection with 1 entry (self => quantity 1).
     * For a Package, returns a collection with all linked products => quantity_required.
     *
     * @return Collection<int, array{product: Product, quantity: int}>
     */
    public function getRequiredProducts(): Collection;

    public function isSellable(): bool;

    /**
     * Whether an operator-wide, listing or underlying-activity blackout covers the date.
     */
    public function isBlackedOutOn(Carbon|string $date): bool;

    /**
     * @return array<string, string> Key is Y-m-d, value is the blackout reason
     */
    public function getBlackoutDates(?Carbon $start = null, ?Carbon $end = null): array;

    public function isPublished(): bool;

    public function getCachedAvgRating(): float;

    /**
     * Generate frozen snapshot array of terms, pricing, and inclusions at booking time.
     *
     * @return array<string, mixed>
     */
    public function generateTermsSnapshot(): array;
}
