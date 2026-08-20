<?php

namespace App\Models\Traits;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

trait HasCancellationPolicy
{
    /**
     * Check if a booking on the given date is eligible for free cancellation.
     */
    public function canCancelForFree(CarbonInterface $requestedDate, ?CarbonInterface $fromTime = null): bool
    {
        $now = $fromTime ? Carbon::instance($fromTime) : now();
        $cutoff = Carbon::instance($requestedDate)->startOfDay()->subHours($this->getFreeCancellationHours());

        return $now->lte($cutoff);
    }

    /**
     * Check if a booking on the given date meets advance booking hours requirement.
     */
    public function meetsAdvanceBookingRequirement(CarbonInterface $requestedDate, ?CarbonInterface $fromTime = null): bool
    {
        $now = $fromTime ? Carbon::instance($fromTime) : now();
        $earliestAllowed = $now->copy()->addHours($this->getAdvanceBookingHours());

        return Carbon::instance($requestedDate)->startOfDay()->gte($earliestAllowed);
    }
}
