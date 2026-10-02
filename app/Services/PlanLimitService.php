<?php

namespace App\Services;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Brings an operator's live storefront in line with the limits of their current plan.
 *
 * Runs after every plan change that can lower limits (lapse to free, downgrade, admin grant),
 * so the rules are never applied in one path and forgotten in another.
 */
class PlanLimitService
{
    /**
     * Draft listings above the plan cap (newest stay live) and pause custom domains
     * the plan no longer includes.
     */
    public function enforce(Operator $operator): void
    {
        $operator->unsetRelation('plan');
        $plan = $operator->getPlan();

        $limit = $plan->package_limit;

        if ($limit !== null) {
            $this->publishedListings($operator)
                ->skip($limit)
                ->each(fn (Model $listing) => $listing->update(['status' => ListingStatus::Draft]));
        }

        if (! $plan->hasFeature('custom_domain')) {
            $operator->domains()
                ->where('type', DomainType::Custom)
                ->where('status', '!=', DomainStatus::Pending)
                ->update([
                    'status' => DomainStatus::Pending,
                    'ssl_issued_at' => null,
                ]);
        }
    }

    /**
     * Whether publishing this listing keeps the operator within their plan's live-listing cap.
     */
    public function canPublish(Operator $operator, Model $listing): bool
    {
        $limit = $operator->getPlan()->package_limit;

        if ($limit === null) {
            return true;
        }

        $alreadyLive = $this->publishedListings($operator)
            ->contains(fn (Model $live): bool => $live->is($listing));

        return $alreadyLive || $this->publishedListings($operator)->count() < $limit;
    }

    /**
     * Published packages and activities, newest first.
     *
     * @return Collection<int, Package|Product>
     */
    private function publishedListings(Operator $operator): Collection
    {
        return $operator->packages()
            ->where('status', ListingStatus::Published)
            ->get()
            ->toBase()
            ->concat($operator->products()->where('status', ListingStatus::Published)->get())
            ->sortByDesc(fn (Model $listing): int => $listing->created_at?->getTimestamp() ?? 0)
            ->values();
    }
}
