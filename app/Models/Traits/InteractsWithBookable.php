<?php

namespace App\Models\Traits;

use App\Enums\ListingStatus;
use App\Models\AvailabilityBlock;
use App\Models\Operator;
use App\Services\MediaStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Shared implementation of the Bookable contract for Product and Package.
 *
 * Only the parts that genuinely differ (title, price, required products, sellability
 * and which blackout blocks apply) stay on the models themselves.
 *
 * @property string $id
 * @property string $operator_id
 * @property string|null $cover_photo
 * @property array<string>|null $gallery
 * @property int $free_cancellation_hours
 * @property int $advance_booking_hours
 * @property array<string>|null $inclusions
 * @property array<string>|null $exclusions
 * @property string|null $terms_and_conditions
 * @property string|null $cancellation_terms
 * @property string $avg_rating
 * @property ListingStatus $status
 * @property-read Operator $operator
 */
trait InteractsWithBookable
{
    /**
     * Product ids whose own blackout blocks also block this bookable.
     *
     * @return list<string>
     */
    abstract protected function blackoutProductIds(): array;

    /**
     * Package id whose package-specific blackout blocks apply, if any.
     */
    abstract protected function blackoutPackageId(): ?string;

    public function getId(): string
    {
        return (string) $this->id;
    }

    public function getCoverPhotoUrlAttribute(): ?string
    {
        return app(MediaStore::class)->url($this->cover_photo);
    }

    /**
     * @return array<string>
     */
    public function getGalleryUrlsAttribute(): array
    {
        if (! is_array($this->gallery)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $path): ?string => app(MediaStore::class)->url($path),
            $this->gallery,
        )));
    }

    public function getOperator(): Operator
    {
        return $this->operator;
    }

    public function getOperatorId(): string
    {
        return (string) $this->operator_id;
    }

    /**
     * @deprecated Use getOperator() instead.
     */
    public function getAgent(): Operator
    {
        return $this->getOperator();
    }

    /**
     * @deprecated Use getOperatorId() instead.
     */
    public function getAgentId(): string
    {
        return $this->getOperatorId();
    }

    public function getFreeCancellationHours(): int
    {
        return (int) $this->free_cancellation_hours;
    }

    public function getAdvanceBookingHours(): int
    {
        return (int) $this->advance_booking_hours;
    }

    /**
     * @return array<int, string>
     */
    public function getInclusions(): array
    {
        return (array) ($this->inclusions ?? []);
    }

    /**
     * @return array<int, string>
     */
    public function getExclusions(): array
    {
        return (array) ($this->exclusions ?? []);
    }

    public function getTermsAndConditions(): ?string
    {
        return $this->terms_and_conditions;
    }

    public function isPublished(): bool
    {
        return $this->status === ListingStatus::Published;
    }

    public function getCachedAvgRating(): float
    {
        return (float) $this->avg_rating;
    }

    /**
     * Frozen copy of price and policies stored on the reservation at booking time.
     *
     * @return array<string, mixed>
     */
    public function generateTermsSnapshot(): array
    {
        return [
            'bookable_type' => $this->getMorphClass(),
            'bookable_id' => $this->getId(),
            'title' => $this->getTitle(),
            'price' => $this->getPrice(),
            'free_cancellation_hours' => $this->getFreeCancellationHours(),
            'advance_booking_hours' => $this->getAdvanceBookingHours(),
            'cancellation_terms' => $this->cancellation_terms,
            'inclusions' => $this->getInclusions(),
            'exclusions' => $this->getExclusions(),
            'terms_and_conditions' => $this->getTermsAndConditions(),
            'frozen_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Whether any operator-wide, package or underlying-activity blackout covers the date.
     */
    public function isBlackedOutOn(Carbon|string $date): bool
    {
        $dateString = Carbon::parse($date)->toDateString();

        return $this->applicableBlackoutBlocks()
            ->whereDate('date_start', '<=', $dateString)
            ->whereDate('date_end', '>=', $dateString)
            ->exists();
    }

    /**
     * Blacked-out dates in a range (default: today until one year ahead).
     *
     * @return array<string, string> Key is Y-m-d, value is the blackout reason
     */
    public function getBlackoutDates(?Carbon $start = null, ?Carbon $end = null): array
    {
        $startDate = $start ?? now()->startOfDay();
        $endDate = $end ?? now()->addYear()->endOfDay();

        $blocks = $this->applicableBlackoutBlocks()
            ->where('date_start', '<=', $endDate->toDateString())
            ->where('date_end', '>=', $startDate->toDateString())
            ->get();

        $dates = [];

        foreach ($blocks as $block) {
            $current = Carbon::parse($block->date_start)->max($startDate);
            $last = Carbon::parse($block->date_end)->min($endDate);

            while ($current->lte($last)) {
                $dates[$current->toDateString()] = $block->reason ?: __('Blackout Date');
                $current = $current->addDay();
            }
        }

        return $dates;
    }

    /**
     * Blocks that apply to this bookable: operator-wide, its own package, or any of its activities.
     *
     * @return Builder<AvailabilityBlock>
     */
    protected function applicableBlackoutBlocks(): Builder
    {
        $packageId = $this->blackoutPackageId();
        $productIds = $this->blackoutProductIds();

        return AvailabilityBlock::query()
            ->where('operator_id', $this->operator_id)
            ->where(function (Builder $query) use ($packageId, $productIds): void {
                $query->where(function (Builder $operatorWide): void {
                    $operatorWide->whereNull('product_id')->whereNull('package_id');
                });

                if ($packageId !== null) {
                    $query->orWhere('package_id', $packageId);
                }

                if ($productIds !== []) {
                    $query->orWhereIn('product_id', $productIds);
                }
            });
    }
}
