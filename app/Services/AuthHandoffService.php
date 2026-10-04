<?php

namespace App\Services;

use App\Models\Operator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * One-time links that carry a just-signed-in operator from the platform host to their
 * slug desk host (login and registration). Each link is signed, expires in 5 minutes,
 * works only on the host it was made for, and can be used exactly once.
 */
class AuthHandoffService
{
    public const TTL_MINUTES = 5;

    /**
     * @param  array<string, scalar>  $parameters
     */
    public function url(string $route, User $user, Operator $operator, ?string $platformDomain = null, array $parameters = []): string
    {
        $root = $operator->slugDeskRoot($platformDomain);
        $nonce = Str::random(40);

        Cache::put($this->cacheKey($nonce), (string) $user->getAuthIdentifier(), now()->addMinutes(self::TTL_MINUTES));

        $relative = URL::temporarySignedRoute(
            $route,
            now()->addMinutes(self::TTL_MINUTES),
            ['user' => $user->getAuthIdentifier(), 'host' => strtolower((string) parse_url($root, PHP_URL_HOST)), 'nonce' => $nonce] + $parameters,
            absolute: false,
        );

        return $root.$relative;
    }

    /**
     * The user the link was made for, or null when it was already used, is for another
     * host, or does not match. Consumes the link.
     */
    public function consume(Request $request): ?User
    {
        $nonce = (string) $request->query('nonce', '');
        $host = strtolower((string) $request->query('host', ''));

        if ($nonce === '' || $host === '' || ! hash_equals($host, strtolower($request->getHost()))) {
            return null;
        }

        $userId = Cache::pull($this->cacheKey($nonce));

        if (! is_string($userId) || ! hash_equals($userId, (string) $request->query('user', ''))) {
            return null;
        }

        return User::query()->find($userId);
    }

    private function cacheKey(string $nonce): string
    {
        return 'auth-handoff:'.hash('sha256', $nonce);
    }
}
