<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The only class that talks to Google's Places API (New). It makes only the free
 * id-only Place Details request; nothing billed is ever called.
 */
class GooglePlacesClient
{
    private const PLACES_URL = 'https://places.googleapis.com/v1/';

    public const PLACE_FOUND = 'found';

    public const PLACE_MISSING = 'missing';

    public const GOOGLE_UNAVAILABLE = 'unavailable';

    public function isConfigured(): bool
    {
        return filled(config('services.google.places_key'));
    }

    /**
     * Whether Google knows a place id, using an id-only request (Google's free
     * "Place Details Essentials (IDs Only)" SKU). Google may answer with a newer id
     * for the same place.
     *
     * @return array{status: string, id: string|null}
     */
    public function checkPlaceId(string $placeId): array
    {
        $response = $this->client()
            ->withHeaders(['X-Goog-FieldMask' => 'id'])
            ->get(self::PLACES_URL.'places/'.rawurlencode($placeId));

        if ($response->status() === 404 || $response->status() === 400) {
            return ['status' => self::PLACE_MISSING, 'id' => null];
        }

        $id = $response->json('id');

        if ($response->failed() || ! is_string($id) || $id === '') {
            return ['status' => self::GOOGLE_UNAVAILABLE, 'id' => null];
        }

        return ['status' => self::PLACE_FOUND, 'id' => $id];
    }

    /**
     * The id Google uses for a stored place id today, or null when the place no longer
     * exists. When Google cannot be reached the stored id is kept, never dropped.
     */
    public function currentPlaceId(string $placeId): ?string
    {
        $check = $this->checkPlaceId($placeId);

        return match ($check['status']) {
            self::PLACE_FOUND => $check['id'],
            self::PLACE_MISSING => null,
            default => $placeId,
        };
    }

    private function client(): PendingRequest
    {
        return Http::timeout(5)
            ->connectTimeout(3)
            // Retry only network errors and Google-side failures, never "not found".
            ->retry(2, 200, fn (Throwable $e): bool => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()), throw: false)
            ->withHeaders([
                'X-Goog-Api-Key' => (string) config('services.google.places_key'),
            ])
            ->acceptJson();
    }
}
