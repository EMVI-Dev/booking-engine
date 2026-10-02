<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The single free-cancellation rule shared by listings (before booking) and reservations (after).
 */
final class CancellationPolicy
{
    /**
     * Last moment a trip on this date can be cancelled for free: start of the trip day minus the free hours.
     */
    public static function cutoff(CarbonInterface|string $tripDate, int $freeHours): Carbon
    {
        return Carbon::parse($tripDate)->startOfDay()->subHours($freeHours);
    }

    public static function isFree(CarbonInterface|string $tripDate, int $freeHours, ?CarbonInterface $at = null): bool
    {
        return ($at ? Carbon::instance($at) : now())->lte(self::cutoff($tripDate, $freeHours));
    }
}
