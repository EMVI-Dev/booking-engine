<?php

namespace App\Services;

use App\Models\Operator;
use App\Services\Integrations\GooglePlacesClient;
use Illuminate\Support\Str;

/**
 * Operator Google listing: find it, snapshot it on the operator, refresh it.
 * HTTP calls to Google go through GooglePlacesClient.
 */
class GooglePlacesService
{
    public function __construct(
        protected GooglePlacesClient $google,
    ) {}

    public const CACHE_DAYS = 30;

    public const REVIEW_LIMIT = 5;

    public function isConfigured(): bool
    {
        return $this->google->isConfigured();
    }

    /**
     * Look up a public Google listing from a Maps link or business name.
     *
     * @return array<string, mixed>|null
     */
    public function lookup(string $query): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $placeId = $this->resolvePlaceId($query);
        if ($placeId === null && $this->looksLikeUrl($query)) {
            return null;
        }

        if ($placeId === null) {
            $placeId = $this->searchPlaceId($query);
        }

        if ($placeId === null) {
            return null;
        }

        return $this->fetchDetails($placeId);
    }

    /**
     * Refresh the cached Google listing for one operator.
     */
    public function refreshOperator(Operator $operator): bool
    {
        $placeId = $operator->googlePlaceId();
        if ($placeId === null) {
            return false;
        }

        $snapshot = $this->fetchDetails($placeId);
        if ($snapshot === null) {
            return false;
        }

        $this->storeSnapshot($operator, $snapshot);

        return true;
    }

    /**
     * Persist a confirmed listing snapshot on the operator.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function storeSnapshot(Operator $operator, array $snapshot): void
    {
        $settings = $operator->settings ?? [];
        $settings['google_place'] = $snapshot;
        $operator->update(['settings' => $settings]);
    }

    public function forgetPlace(Operator $operator): void
    {
        $settings = $operator->settings ?? [];
        unset($settings['google_place']);
        $operator->update(['settings' => $settings]);
    }

    public function writeReviewUrl(string $placeId): string
    {
        return 'https://search.google.com/local/writereview?placeid='.urlencode($placeId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchDetails(string $placeId): ?array
    {
        $place = $this->google->placeDetails($placeId);

        return $place === null ? null : $this->normalizePlace($place);
    }

    private function searchPlaceId(string $query): ?string
    {
        return $this->google->searchPlaceId($query);
    }

    private function resolvePlaceId(string $query): ?string
    {
        $direct = $this->extractPlaceId($query);
        if ($direct !== null) {
            return $direct;
        }

        if (! $this->looksLikeUrl($query)) {
            return null;
        }

        $finalUrl = $this->google->expandMapsLink($query);

        if ($finalUrl === null) {
            return null;
        }

        return $this->extractPlaceId($finalUrl);
    }

    private function extractPlaceId(string $value): ?string
    {
        if (preg_match('/^ChI[\w-]{10,}$/', $query = trim($value)) === 1) {
            return $query;
        }

        if (preg_match('/(?:place_id|query_place_id)=([A-Za-z0-9_-]+)/', $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/place_id:([A-Za-z0-9_-]+)/', $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/!1s(ChI[\w-]{10,})/', $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/(?:^|[\/])(ChI[\w-]{10,})(?:[\/?#]|$)/', $value, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $place
     * @return array<string, mixed>|null
     */
    private function normalizePlace(array $place): ?array
    {
        $placeId = trim((string) ($place['id'] ?? ''));
        $name = trim((string) ($place['displayName']['text'] ?? ''));
        if ($placeId === '' || $name === '') {
            return null;
        }

        $reviews = collect($place['reviews'] ?? [])
            ->filter(fn ($review) => is_array($review))
            ->take(self::REVIEW_LIMIT)
            ->map(function (array $review): array {
                $text = trim((string) ($review['text']['text'] ?? $review['originalText']['text'] ?? ''));

                return [
                    'author' => trim((string) ($review['authorAttribution']['displayName'] ?? '')),
                    'rating' => (int) ($review['rating'] ?? 0),
                    'text' => $text,
                    'relative_time' => trim((string) ($review['relativePublishTimeDescription'] ?? '')),
                    'url' => trim((string) ($review['googleMapsUri'] ?? $review['authorAttribution']['uri'] ?? '')),
                ];
            })
            ->filter(fn (array $review) => $review['author'] !== '' || $review['text'] !== '')
            ->values()
            ->all();

        return [
            'place_id' => $placeId,
            'name' => $name,
            'address' => trim((string) ($place['formattedAddress'] ?? '')),
            'rating' => isset($place['rating']) ? (float) $place['rating'] : null,
            'review_count' => isset($place['userRatingCount']) ? (int) $place['userRatingCount'] : null,
            'maps_url' => trim((string) ($place['googleMapsUri'] ?? '')),
            'write_review_url' => $this->writeReviewUrl($placeId),
            'reviews' => $reviews,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    private function looksLikeUrl(string $value): bool
    {
        return Str::startsWith($value, ['http://', 'https://']);
    }
}
