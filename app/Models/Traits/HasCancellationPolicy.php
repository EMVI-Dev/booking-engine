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
     * Check if a booking on the given date meets advance booking hours requirement.
     */
    public function meetsAdvanceBookingRequirement(CarbonInterface $requestedDate, ?CarbonInterface $fromTime = null): bool
    {
        $now = $fromTime ? Carbon::instance($fromTime) : now();
        $earliestAllowed = $now->copy()->addHours($this->getAdvanceBookingHours());

        return Carbon::instance($requestedDate)->startOfDay()->gte($earliestAllowed);
    }
}
