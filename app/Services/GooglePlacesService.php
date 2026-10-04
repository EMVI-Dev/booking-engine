<?php

namespace App\Services;

use App\Models\Operator;
use App\Services\Integrations\GooglePlacesClient;

/**
 * Operator Google listing, built entirely on Google's free services:
 *
 * - Only the place ID is stored (the one Places value Google allows keeping).
 * - The ID is checked with the free id-only Place Details request (connect + monthly check).
 * - The listing is shown with the free Maps Embed card plus plain Google links
 *   (read reviews, write a review). No billed Places request is ever made.
 *
 * HTTP calls to Google go through GooglePlacesClient.
 */
class GooglePlacesService
{
    public function __construct(
        protected GooglePlacesClient $google,
    ) {}

    /**
     * Google's own tool for finding a business's place ID (shown to operators).
     */
    public const PLACE_ID_FINDER_URL = 'https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder';

    /**
     * Whether place IDs can be checked (server key set).
     */
    public function isConfigured(): bool
    {
        return $this->google->isConfigured();
    }

    /**
     * Whether the free Google card can be shown (public embed key set).
     */
    public function canEmbed(): bool
    {
        return filled(config('services.google.maps_embed_key'));
    }

    /**
     * Check a Place ID the operator pasted, with Google's free id-only request.
     *
     * @return array{status: string, place_id: string|null} status is one of the
     *                                                      GooglePlacesClient::PLACE_* / GOOGLE_UNAVAILABLE constants
     */
    public function checkPastedPlaceId(string $input): array
    {
        $placeId = $this->extractPlaceId($input);

        if ($placeId === null) {
            return ['status' => GooglePlacesClient::PLACE_MISSING, 'place_id' => null];
        }

        $check = $this->google->checkPlaceId($placeId);

        return ['status' => $check['status'], 'place_id' => $check['id']];
    }

    /**
     * Connect a listing: only the place id is stored.
     */
    public function connect(Operator $operator, string $placeId): void
    {
        $settings = $operator->settings ?? [];
        $settings['google_place'] = [
            'place_id' => $placeId,
            'connected_at' => now()->toIso8601String(),
        ];
        $operator->update(['settings' => $settings]);
    }

    public function forgetPlace(Operator $operator): void
    {
        $settings = $operator->settings ?? [];
        unset($settings['google_place']);
        $operator->update(['settings' => $settings]);
    }

    /**
     * Re-check a stored place id (Google may retire or replace ids over time), with the
     * free id-only request. Also strips anything other than the id that older versions
     * saved. Returns false when Google no longer knows the id, so the caller can disconnect it.
     */
    public function verifyStoredPlace(Operator $operator): bool
    {
        $placeId = $operator->googlePlaceId();

        if ($placeId === null) {
            return true;
        }

        $currentId = $this->google->currentPlaceId($placeId);

        if ($currentId === null) {
            return false;
        }

        $stored = $operator->settings['google_place'] ?? [];
        if ($currentId !== $placeId || array_diff(array_keys((array) $stored), ['place_id', 'connected_at']) !== []) {
            $settings = $operator->settings ?? [];
            $settings['google_place'] = [
                'place_id' => $currentId,
                'connected_at' => (string) ($stored['connected_at'] ?? now()->toIso8601String()),
            ];
            $operator->update(['settings' => $settings]);
        }

        return true;
    }

    /**
     * Google's embedded place card (name, address, stars, review count, map). Maps Embed
     * is free and unlimited. Null when no embed key is set.
     */
    public function embedUrl(string $placeId): ?string
    {
        $key = (string) config('services.google.maps_embed_key');

        if ($key === '') {
            return null;
        }

        return 'https://www.google.com/maps/embed/v1/place?key='.urlencode($key).'&q=place_id:'.urlencode($placeId);
    }

    /**
     * The listing on Google Maps, where guests read the reviews (Maps URLs; no key, no cost).
     */
    public function mapsUrl(string $placeId, string $name = 'Google'): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($name !== '' ? $name : 'Google').'&query_place_id='.urlencode($placeId);
    }

    /**
     * Google's "write a review" page for the listing (a plain link; no cost).
     */
    public function writeReviewUrl(string $placeId): string
    {
        return 'https://search.google.com/local/writereview?placeid='.urlencode($placeId);
    }

    /**
     * The Place ID in what the operator pasted: the bare id, or text that contains it
     * (e.g. "Place ID: ChIJ..." copied from Google's Place ID Finder).
     */
    private function extractPlaceId(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^[A-Za-z0-9_-]{20,300}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/\b(ChI[A-Za-z0-9_-]{17,})\b/', $value, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
