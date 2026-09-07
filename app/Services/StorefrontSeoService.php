<?php

namespace App\Services;

use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorefrontSeoService
{
    /**
     * Absolute image URL and card size for WhatsApp / Facebook / iMessage.
     *
     * @return array{url: string, alt: string, card: string, width: int, height: int}
     */
    public function shareImage(Operator $operator, ?string $listingCoverPath = null): array
    {
        if (filled($listingCoverPath)) {
            $url = $this->absoluteMediaUrl($listingCoverPath);

            if ($url !== null) {
                return $this->largeCard($url, $operator->name);
            }
        }

        if (filled($operator->banner_path)) {
            $url = $this->absoluteMediaUrl($operator->banner_path);

            if ($url !== null) {
                return $this->largeCard($url, $operator->name);
            }
        }

        $logoPath = $operator->logo;

        if (filled($logoPath)) {
            $url = $this->absoluteMediaUrl($logoPath);

            if ($url !== null) {
                return [
                    'url' => $url,
                    'alt' => $operator->name,
                    'card' => 'summary',
                    'width' => 400,
                    'height' => 400,
                ];
            }
        }

        return [
            'url' => url(asset('favicon.png')),
            'alt' => $operator->name,
            'card' => 'summary',
            'width' => 400,
            'height' => 400,
        ];
    }

    /**
     * Pick a wide photo from published listings when the operator has no banner.
     *
     * @param  iterable<int, Package>  $packages
     * @param  iterable<int, Product>  $products
     * @return array{url: string, alt: string, card: string, width: int, height: int}
     */
    public function homeShareImage(Operator $operator, iterable $packages = [], iterable $products = []): array
    {
        $cover = $this->listingCollection($packages)->first(fn (Package $package): bool => filled($package->cover_photo))?->cover_photo
            ?? $this->listingCollection($products)->first(fn (Product $product): bool => filled($product->cover_photo))?->cover_photo;

        return $this->shareImage($operator, $cover);
    }

    public function homeTitle(Operator $operator): string
    {
        $headline = $operator->settings['storefront']['hero_headline'] ?? null;

        if (filled($headline)) {
            return Str::limit(trim((string) $headline), 70);
        }

        return $operator->name;
    }

    public function homeDescription(Operator $operator): string
    {
        $tagline = $operator->settings['storefront']['hero_tagline'] ?? null;
        $source = filled($tagline) ? (string) $tagline : (string) ($operator->bio ?: '');

        if ($source === '') {
            $source = __('Book tours and activities with :name. See prices and reserve a spot online.', [
                'name' => $operator->name,
            ]);
        }

        return $this->limitPlainText($source, 160);
    }

    public function listingDescription(?string $text, string $fallback): string
    {
        return $this->limitPlainText(filled($text) ? $text : $fallback, 160);
    }

    public function absoluteMediaUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $storageUrl = Storage::disk('public')->url($path);

        if (str_starts_with($storageUrl, 'http://') || str_starts_with($storageUrl, 'https://')) {
            $pathPart = parse_url($storageUrl, PHP_URL_PATH);

            return is_string($pathPart) && $pathPart !== '' ? url($pathPart) : $storageUrl;
        }

        return url($storageUrl);
    }

    /**
     * @param  iterable<int, Package>  $packages
     * @return array<string, mixed>
     */
    public function homeGraph(Operator $operator, iterable $packages = []): array
    {
        $share = $this->homeShareImage($operator, $packages);
        $items = $this->listingCollection($packages)->take(8)->values();

        return $this->graph([
            $this->travelAgency($operator, $share['url']),
            $this->webSite($operator),
            $items->isEmpty() ? null : [
                '@type' => 'ItemList',
                '@id' => url('/').'#catalog',
                'name' => $operator->name.' tours',
                'itemListElement' => $items->map(fn (Package $package, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => $this->touristTrip($operator, $package),
                ])->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function packageGraph(Operator $operator, Package $package): array
    {
        $share = $this->shareImage($operator, $package->cover_photo);

        return $this->graph([
            $this->breadcrumbs([
                ['name' => __('Home'), 'url' => url('/')],
                ['name' => __('Tours'), 'url' => route('storefront.packages')],
                ['name' => $package->title, 'url' => route('storefront.package', $package->slug)],
            ]),
            $this->travelAgency($operator, $share['url']),
            $this->touristTrip($operator, $package),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function productGraph(Operator $operator, Product $product): array
    {
        $share = $this->shareImage($operator, $product->cover_photo);

        return $this->graph([
            $this->breadcrumbs([
                ['name' => __('Home'), 'url' => url('/')],
                ['name' => __('Activities'), 'url' => route('storefront.products')],
                ['name' => $product->name, 'url' => route('storefront.product', $product->slug)],
            ]),
            $this->travelAgency($operator, $share['url']),
            $this->productOffer($operator, $product),
        ]);
    }

    /**
     * @param  iterable<int, Package>  $packages
     * @return array<string, mixed>
     */
    public function packageCatalogGraph(Operator $operator, iterable $packages): array
    {
        $items = $this->listingCollection($packages);

        return $this->graph([
            $this->breadcrumbs([
                ['name' => __('Home'), 'url' => url('/')],
                ['name' => __('Tours'), 'url' => route('storefront.packages')],
            ]),
            $this->travelAgency($operator, $this->homeShareImage($operator, $items)['url']),
            [
                '@type' => 'ItemList',
                'name' => $operator->name.' tours',
                'numberOfItems' => $items->count(),
                'itemListElement' => $items->map(fn (Package $package, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => $this->touristTrip($operator, $package),
                ])->all(),
            ],
        ]);
    }

    /**
     * @param  iterable<int, Product>  $products
     * @return array<string, mixed>
     */
    public function productCatalogGraph(Operator $operator, iterable $products): array
    {
        $items = $this->listingCollection($products);

        return $this->graph([
            $this->breadcrumbs([
                ['name' => __('Home'), 'url' => url('/')],
                ['name' => __('Activities'), 'url' => route('storefront.products')],
            ]),
            $this->travelAgency($operator, $this->homeShareImage($operator, [], $items)['url']),
            [
                '@type' => 'ItemList',
                'name' => $operator->name.' activities',
                'numberOfItems' => $items->count(),
                'itemListElement' => $items->map(fn (Product $product, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => $this->productOffer($operator, $product),
                ])->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function termsGraph(Operator $operator): array
    {
        return $this->graph([
            $this->breadcrumbs([
                ['name' => __('Home'), 'url' => url('/')],
                ['name' => __('Terms'), 'url' => route('storefront.terms')],
            ]),
            $this->travelAgency($operator, $this->shareImage($operator)['url']),
            [
                '@type' => 'WebPage',
                '@id' => route('storefront.terms').'#webpage',
                'name' => __('Booking terms — :name', ['name' => $operator->name]),
                'url' => route('storefront.terms'),
                'isPartOf' => ['@id' => url('/').'#website'],
            ],
        ]);
    }

    /**
     * @return array{url: string, alt: string, card: string, width: int, height: int}
     */
    private function largeCard(string $url, string $alt): array
    {
        return [
            'url' => $url,
            'alt' => $alt,
            'card' => 'summary_large_image',
            'width' => 1200,
            'height' => 630,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function travelAgency(Operator $operator, ?string $imageUrl = null): array
    {
        $logo = $this->absoluteMediaUrl($operator->logo);

        return [
            '@type' => 'TravelAgency',
            '@id' => url('/').'#agency',
            'name' => $operator->name,
            'url' => url('/'),
            'description' => $this->homeDescription($operator),
            'image' => $imageUrl ?: $logo,
            'logo' => $logo,
            'telephone' => $operator->contact_whatsapp,
            'email' => $operator->booking_notification_email,
            'priceRange' => 'Rp',
            'areaServed' => [
                '@type' => 'Country',
                'name' => 'Indonesia',
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'addressCountry' => 'ID',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function webSite(Operator $operator): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'url' => url('/'),
            'name' => $operator->name,
            'publisher' => [
                '@id' => url('/').'#agency',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function touristTrip(Operator $operator, Package $package): array
    {
        $image = $this->absoluteMediaUrl($package->cover_photo);

        return [
            '@type' => 'TouristTrip',
            '@id' => route('storefront.package', $package->slug).'#trip',
            'name' => $package->title,
            'description' => $this->listingDescription($package->description, $package->title),
            'url' => route('storefront.package', $package->slug),
            'image' => $image,
            'touristType' => $package->category,
            'itinerary' => filled($package->location) ? [
                '@type' => 'Place',
                'name' => $package->location,
            ] : null,
            'provider' => [
                '@id' => url('/').'#agency',
                'name' => $operator->name,
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => route('storefront.package', $package->slug),
                'price' => (float) $package->price,
                'priceCurrency' => 'IDR',
                'availability' => 'https://schema.org/InStock',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productOffer(Operator $operator, Product $product): array
    {
        return [
            '@type' => 'Product',
            '@id' => route('storefront.product', $product->slug).'#product',
            'name' => $product->name,
            'description' => $this->listingDescription($product->description, $product->name),
            'url' => route('storefront.product', $product->slug),
            'image' => $this->absoluteMediaUrl($product->cover_photo),
            'category' => $product->category,
            'brand' => [
                '@type' => 'Brand',
                'name' => $operator->name,
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => route('storefront.product', $product->slug),
                'price' => (float) $product->price,
                'priceCurrency' => 'IDR',
                'availability' => 'https://schema.org/InStock',
            ],
        ];
    }

    /**
     * @param  iterable<int, mixed>  $listings
     */
    private function listingCollection(iterable $listings): Collection
    {
        if ($listings instanceof Collection) {
            return $listings->values();
        }

        if ($listings instanceof Paginator) {
            return collect($listings->items());
        }

        return collect($listings)->values();
    }

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    private function breadcrumbs(array $crumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>|null>  $nodes
     * @return array<string, mixed>
     */
    private function graph(array $nodes): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_map(
                fn (array $node): array => $this->withoutEmpty($node),
                array_values(array_filter($nodes)),
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutEmpty(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $value = $this->withoutEmpty($value);

                if ($value === []) {
                    continue;
                }
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private function limitPlainText(string $text, int $limit): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        return Str::limit($plain, $limit);
    }
}
