<?php

namespace App\Concerns;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

trait ManagesPackageProductBundle
{
    public string $bundleSearch = '';

    public bool $bundlePriceSuggestionDismissed = false;

    public const BUNDLE_BROWSE_LIMIT = 8;

    public const BUNDLE_SEARCH_LIMIT = 25;

    public const BUNDLE_PRICE_ROUND_TO = 10000;

    public const BUNDLE_SUGGEST_DISCOUNT = 0.15;

    public const BUNDLE_RANGE_LOW_DISCOUNT = 0.20;

    public const BUNDLE_RANGE_HIGH_DISCOUNT = 0.10;

    public const GALLERY_PHOTOS_PER_ACTIVITY = 2;

    /**
     * Source activity gallery paths already copied into existingGallery (index => source).
     *
     * @var array<int, string>
     */
    public array $galleryImportSources = [];

    public function toggleProductSelection(string $productId): void
    {
        $product = $this->availableProducts->firstWhere('id', $productId);

        if (! $product) {
            return;
        }

        if (isset($this->selectedProducts[$productId])) {
            unset($this->selectedProducts[$productId]);
            $this->bundlePriceSuggestionDismissed = false;

            return;
        }

        $this->selectedProducts[$productId] = 1;
        $this->bundleSearch = '';
        $this->bundlePriceSuggestionDismissed = false;
    }

    public function updatedSelectedProducts(): void
    {
        $this->bundlePriceSuggestionDismissed = false;
    }

    public function applySuggestedBundlePrice(): void
    {
        $suggested = $this->bundledSuggestedPrice;

        if ($suggested <= 0) {
            return;
        }

        $this->price = $suggested;
        $this->bundlePriceSuggestionDismissed = true;

        $this->dispatch('toast', message: __('Package price set to the suggested amount.'), type: 'success');
    }

    public function fillGalleryFromActivities(): void
    {
        if ($this->selectedProducts === [] || ! $this->currentOperator) {
            return;
        }

        $products = Product::query()
            ->where('operator_id', $this->currentOperator->id)
            ->whereIn('id', array_keys($this->selectedProducts))
            ->get(['id', 'gallery']);

        $directory = $this->operatorMediaDirectory('packages/gallery');
        $alreadyImported = array_values($this->galleryImportSources);
        $added = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $gallery = is_array($product->gallery) ? array_values(array_filter($product->gallery)) : [];
            $paths = array_slice($gallery, 0, self::GALLERY_PHOTOS_PER_ACTIVITY);

            foreach ($paths as $path) {
                $source = (string) $path;

                if (in_array($source, $alreadyImported, true)) {
                    $skipped++;

                    continue;
                }

                $copied = $this->media()->copy($source, $directory);
                $this->existingGallery[] = $copied;
                $this->galleryImportSources[array_key_last($this->existingGallery)] = $source;
                $alreadyImported[] = $source;
                $added++;
            }
        }

        if ($added === 0 && $skipped > 0) {
            $this->dispatch('toast', message: __('Those activity photos are already in the gallery.'), type: 'info');

            return;
        }

        if ($added === 0) {
            $this->dispatch('toast', message: __('No gallery photos found on the selected activities.'), type: 'warning');

            return;
        }

        $this->dispatch('toast', message: __('Added :count photos from selected activities.', ['count' => $added]), type: 'success');
    }

    public function applyActivityCover(string $productId): void
    {
        if ($this->selectedProducts === [] || ! $this->currentOperator) {
            return;
        }

        if (! isset($this->selectedProducts[$productId])) {
            return;
        }

        $product = Product::query()
            ->where('operator_id', $this->currentOperator->id)
            ->whereKey($productId)
            ->first(['id', 'cover_photo']);

        if (! $product || ! filled($product->cover_photo)) {
            $this->dispatch('toast', message: __('That activity has no cover photo.'), type: 'warning');

            return;
        }

        if (property_exists($this, 'existingCoverPhoto') && filled($this->existingCoverPhoto)) {
            $previous = (string) $this->existingCoverPhoto;
            $isPersistedPackageCover = isset($this->package)
                && filled($this->package->cover_photo)
                && $previous === $this->package->cover_photo;

            if (! $isPersistedPackageCover) {
                $this->media()->delete($previous);
            }
        }

        $this->coverPhoto = null;
        $this->existingCoverPhoto = $this->media()->copy(
            (string) $product->cover_photo,
            $this->operatorMediaDirectory('packages/covers')
        );

        $this->dispatch('close-modal', 'choose-activity-cover');
        $this->dispatch('toast', message: __('Cover photo set from the activity.'), type: 'success');
    }

    /**
     * Remove a staged gallery path and keep import-source indexes aligned.
     */
    protected function pullExistingGalleryImage(int $index): ?string
    {
        if (! isset($this->existingGallery[$index])) {
            return null;
        }

        $pairs = [];

        foreach ($this->existingGallery as $i => $galleryPath) {
            $pairs[] = [
                'path' => $galleryPath,
                'source' => $this->galleryImportSources[$i] ?? null,
            ];
        }

        $removed = $pairs[$index]['path'];
        array_splice($pairs, $index, 1);

        $this->existingGallery = [];
        $this->galleryImportSources = [];

        foreach (array_values($pairs) as $i => $pair) {
            $this->existingGallery[$i] = $pair['path'];

            if (is_string($pair['source']) && $pair['source'] !== '') {
                $this->galleryImportSources[$i] = $pair['source'];
            }
        }

        return $removed;
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function activityCoverChoices(): Collection
    {
        if ($this->selectedProducts === [] || ! $this->currentOperator) {
            return collect();
        }

        return Product::query()
            ->where('operator_id', $this->currentOperator->id)
            ->whereIn('id', array_keys($this->selectedProducts))
            ->whereNotNull('cover_photo')
            ->where('cover_photo', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'cover_photo']);
    }

    /**
     * Round down to the nearest Rp 10.000 for merchandising.
     */
    protected function floorBundlePrice(float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $rounded = floor($amount / self::BUNDLE_PRICE_ROUND_TO) * self::BUNDLE_PRICE_ROUND_TO;

        return max((float) self::BUNDLE_PRICE_ROUND_TO, $rounded);
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function bundledProducts(): Collection
    {
        return collect(array_keys($this->selectedProducts))
            ->map(fn (mixed $id): ?Product => $this->availableProducts->firstWhere('id', $id))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function bundleCatalog(): Collection
    {
        $remaining = $this->availableProducts
            ->reject(fn (Product $product): bool => isset($this->selectedProducts[$product->id]));

        $query = trim($this->bundleSearch);

        if ($query !== '') {
            $needle = Str::lower($query);

            return $remaining
                ->filter(fn (Product $product): bool => Str::contains(Str::lower($product->name), $needle)
                    || Str::contains(Str::lower((string) $product->category), $needle))
                ->take(self::BUNDLE_SEARCH_LIMIT)
                ->values();
        }

        if ($remaining->count() <= self::BUNDLE_BROWSE_LIMIT) {
            return $remaining->values();
        }

        return collect();
    }

    #[Computed]
    public function bundleRemainingCount(): int
    {
        return $this->availableProducts
            ->reject(fn (Product $product): bool => isset($this->selectedProducts[$product->id]))
            ->count();
    }

    #[Computed]
    public function bundleRequiresSearch(): bool
    {
        return trim($this->bundleSearch) === ''
            && $this->bundleRemainingCount > self::BUNDLE_BROWSE_LIMIT;
    }

    #[Computed]
    public function bundledSeparateTotal(): float
    {
        if ($this->selectedProducts === []) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($this->selectedProducts as $productId => $qty) {
            $product = $this->availableProducts->firstWhere('id', $productId);

            if (! $product) {
                continue;
            }

            $total += (float) $product->price * max(1, (int) $qty);
        }

        return $total;
    }

    #[Computed]
    public function bundledPriceRangeLow(): float
    {
        if ($this->bundledSeparateTotal <= 0) {
            return 0.0;
        }

        return $this->floorBundlePrice($this->bundledSeparateTotal * (1 - self::BUNDLE_RANGE_LOW_DISCOUNT));
    }

    #[Computed]
    public function bundledPriceRangeHigh(): float
    {
        if ($this->bundledSeparateTotal <= 0) {
            return 0.0;
        }

        return $this->floorBundlePrice($this->bundledSeparateTotal * (1 - self::BUNDLE_RANGE_HIGH_DISCOUNT));
    }

    #[Computed]
    public function bundledSuggestedPrice(): float
    {
        if ($this->bundledSeparateTotal <= 0) {
            return 0.0;
        }

        return $this->floorBundlePrice($this->bundledSeparateTotal * (1 - self::BUNDLE_SUGGEST_DISCOUNT));
    }

    #[Computed]
    public function showsBundlePriceWarning(): bool
    {
        if ($this->selectedProducts === [] || $this->bundledSeparateTotal <= 0) {
            return false;
        }

        return (float) $this->price >= $this->bundledSeparateTotal;
    }

    #[Computed]
    public function showsBundlePriceHint(): bool
    {
        if ($this->selectedProducts === [] || $this->bundledSeparateTotal <= 0) {
            return false;
        }

        if ($this->showsBundlePriceWarning) {
            return true;
        }

        return ! $this->bundlePriceSuggestionDismissed;
    }
}
