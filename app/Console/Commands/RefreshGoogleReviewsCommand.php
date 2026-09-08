<?php

namespace App\Console\Commands;

use App\Models\Operator;
use App\Services\GooglePlacesService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('google:refresh-reviews')]
#[Description('Refresh cached Google listing reviews for connected operators.')]
class RefreshGoogleReviewsCommand extends Command
{
    public function handle(GooglePlacesService $places): int
    {
        if (! $places->isConfigured()) {
            $this->warn('Google Places is not set up. Skipping review refresh.');

            return self::SUCCESS;
        }

        $refreshed = 0;

        Operator::query()
            ->whereNotNull('settings')
            ->orderBy('id')
            ->chunkById(50, function ($operators) use ($places, &$refreshed): void {
                foreach ($operators as $operator) {
                    if (! $operator->hasFeature('google_reviews') || $operator->googlePlaceId() === null) {
                        continue;
                    }

                    if ($places->refreshOperator($operator)) {
                        $refreshed++;
                    }
                }
            });

        $this->info("Refreshed Google reviews for {$refreshed} operators.");

        return self::SUCCESS;
    }
}
