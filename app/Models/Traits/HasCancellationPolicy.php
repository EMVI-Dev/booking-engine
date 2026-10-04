<?php

namespace App\Models\Traits;

use App\Services\CancellationPolicy;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

trait HasCancellationPolicy
{
    /**
     * Check if a booking on the given date is eligible for free cancellation.
     */
    public function canCancelForFree(CarbonInterface $requestedDate, ?CarbonInterface $fromTime = null): bool
    {
        return CancellationPolicy::isFree($requestedDate, $this->getFreeCancellationHours(), $fromTime);
    }

    /**
     * The first trip date a guest may book: the date reached after the advance-booking hours
     * from now. The single rule used by the date picker, the booking box and createHold().
     */
    public function earliestBookableDate(?CarbonInterface $fromTime = null): CarbonInterface
    {
        $now = $fromTime ?? now();

        return $now->copy()->addHours($this->getAdvanceBookingHours())->startOfDay();
    }

    /**
     * Check if a booking on the given date meets advance booking hours requirement.
     */
    public function meetsAdvanceBookingRequirement(CarbonInterface $requestedDate, ?CarbonInterface $fromTime = null): bool
    {
        return Carbon::instance($requestedDate)->startOfDay()->gte($this->earliestBookableDate($fromTime));
    }
}
