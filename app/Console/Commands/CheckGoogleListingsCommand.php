<?php

namespace App\Console\Commands;

use App\Models\Operator;
use App\Services\GooglePlacesService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('google:check-listings')]
#[Description('Re-check stored Google place ids: follow ids Google replaced, disconnect ids that no longer exist, and drop any listing content older versions stored.')]
class CheckGoogleListingsCommand extends Command
{
    public function handle(GooglePlacesService $places): int
    {
        if (! $places->isConfigured()) {
            $this->warn('Google Places is not set up. Skipping.');

            return self::SUCCESS;
        }

        $checked = 0;
        $removed = 0;

        Operator::query()
            ->whereNotNull('settings->google_place')
            ->orderBy('id')
            ->chunkById(50, function ($operators) use ($places, &$checked, &$removed): void {
                foreach ($operators as $operator) {
                    if ($operator->googlePlaceId() === null) {
                        continue;
                    }

                    $checked++;

                    if (! $places->verifyStoredPlace($operator)) {
                        $places->forgetPlace($operator);
                        $removed++;
                    }
                }
            });

        $this->info("Checked {$checked} Google listings; disconnected {$removed} that Google no longer has.");

        return self::SUCCESS;
    }
}
