<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The only class that talks to Google: the Places API (New) and Google Maps share links.
 * Returns Google's raw place arrays; GooglePlacesService turns them into listing snapshots.
 */
class GooglePlacesClient
{
    private const PLACES_URL = 'https://places.googleapis.com/v1/';

    /**
     * Hosts a pasted Maps link may point at. Anything else is never fetched, so an
     * operator cannot make the server call internal or arbitrary addresses.
     *
     * @var list<string>
     */
    private const MAPS_LINK_HOSTS = ['maps.app.goo.gl', 'goo.gl', 'g.page', 'maps.google.com', 'www.google.com', 'google.com', 'share.google'];

    public function isConfigured(): bool
    {
        return filled(config('services.google.places_key'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function placeDetails(string $placeId): ?array
    {
        $response = $this->client()
            ->withHeaders([
                'X-Goog-FieldMask' => 'id,displayName,formattedAddress,rating,userRatingCount,googleMapsUri,reviews',
            ])
            ->get(self::PLACES_URL.'places/'.rawurlencode($placeId));

        if ($response->failed()) {
            return null;
        }

        $place = $response->json();

        return is_array($place) ? $place : null;
    }

    /**
     * Best match place id for a business name, or null.
     */
    public function searchPlaceId(string $query): ?string
    {
        $response = $this->client()
            ->withHeaders([
                'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress',
            ])
            ->post(self::PLACES_URL.'places:searchText', [
                'textQuery' => $query,
                'pageSize' => 1,
            ]);

        if ($response->failed()) {
            return null;
        }

        $placeId = $response->json('places.0.id');

        return is_string($placeId) && $placeId !== '' ? $placeId : null;
    }

    /**
     * Follow a Google Maps share link (e.g. maps.app.goo.gl/...) to its final URL.
     * Returns null for non-Google links or when the link cannot be opened.
     */
    public function expandMapsLink(string $url): ?string
    {
        if (! $this->isMapsLink($url)) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(3)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'track_redirects' => true,
                        'protocols' => ['https'],
                        'on_redirect' => function ($request, $response, $uri): void {
                            if (! $this->isMapsLink((string) $uri)) {
                                throw new ConnectionException('Maps link redirected off Google.');
                            }
                        },
                    ],
                ])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        return (string) ($response->effectiveUri() ?? $url);
    }

    public function isMapsLink(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['port']) || isset($parts['user'])) {
            return false;
        }

        return in_array(strtolower((string) ($parts['host'] ?? '')), self::MAPS_LINK_HOSTS, true);
    }

    private function client(): PendingRequest
    {
        return Http::timeout(8)
            ->connectTimeout(3)
            ->retry(2, 200, throw: false)
            ->withHeaders([
                'X-Goog-Api-Key' => (string) config('services.google.places_key'),
            ])
            ->acceptJson();
    }
}
