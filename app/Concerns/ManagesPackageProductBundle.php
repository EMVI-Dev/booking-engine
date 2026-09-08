<?php

namespace App\Concerns;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

trait ManagesPackageProductBundle
{
    public string $bundleSearch = '';

    public const BUNDLE_BROWSE_LIMIT = 8;

    public const BUNDLE_SEARCH_LIMIT = 25;

    public function toggleProductSelection(string $productId): void
    {
        $product = $this->availableProducts->firstWhere('id', $productId);

        if (! $product) {
            return;
        }

        if (isset($this->selectedProducts[$productId])) {
            unset($this->selectedProducts[$productId]);

            return;
        }

        $this->selectedProducts[$productId] = 1;
        $this->bundleSearch = '';
    }

    public function seedInclusionsFromProducts(): void
    {
        if (empty($this->selectedProducts) || ! $this->currentOperator) {
            return;
        }

        $productNames = Product::query()
            ->where('operator_id', $this->currentOperator->id)
            ->whereIn('id', array_keys($this->selectedProducts))
            ->pluck('name')
            ->all();

        $existing = array_filter(array_map('trim', explode(',', $this->inclusions)));
        $combined = array_unique(array_merge($existing, $productNames));

        $this->inclusions = implode(', ', $combined);
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
}
