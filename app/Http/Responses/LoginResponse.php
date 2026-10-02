<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Services\DomainResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Send an operator to their slug desk host after login.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return $this->resolveRedirect($request);
    }

    /**
     * Resolve the redirection target for the authenticated operator user.
     */
    public function resolveRedirect(Request $request): Response
    {
        $user = $request->user();

        // Admins (after a passkey or two-factor sign-in) belong on the admin desk, not an operator shop.
        if ($user instanceof User && $user->isAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        $operator = $user instanceof User ? $user->currentOperator() : null;

        if ($operator === null || blank($operator->slug)) {
            return redirect()->intended(Fortify::redirects('login'));
        }

        $resolver = app(DomainResolverService::class);
        $currentHost = strtolower(trim(explode(':', (string) ($request->header('Host') ?: $request->getHost()))[0]));

        $platformDomain = in_array($currentHost, $resolver->knownPlatformSuffixes(), true)
            ? $currentHost
            : $resolver->getPlatformDomain();

        $targetHost = strtolower($operator->slug.'.'.$platformDomain);

        $isOperatorHost = $currentHost === $targetHost
            || $operator->domains()->where('domain', $currentHost)->exists();

        if ($isOperatorHost) {
            return redirect()->intended(Fortify::redirects('login'));
        }

        $intended = session()->pull('url.intended');
        $intendedPath = null;
        if (is_string($intended) && filled($intended)) {
            $parsedPath = parse_url($intended, PHP_URL_PATH);
            $parsedQuery = parse_url($intended, PHP_URL_QUERY);
            if (is_string($parsedPath) && str_starts_with($parsedPath, '/') && ! str_starts_with($parsedPath, '//') && $parsedPath !== '/login') {
                $intendedPath = $parsedPath.(is_string($parsedQuery) && filled($parsedQuery) ? '?'.$parsedQuery : '');
            }
        }

        $handoffParams = [
            'user' => $user->getAuthIdentifier(),
            'remember' => $request->boolean('remember') ? 1 : 0,
        ];

        if ($intendedPath) {
            $handoffParams['intended'] = $intendedPath;
        }

        $relativeHandoff = URL::temporarySignedRoute(
            'auth.login-handoff',
            now()->addMinutes(5),
            $handoffParams,
            absolute: false,
        );

        $deskUrl = $operator->slugDeskRoot($platformDomain).$relativeHandoff;

        return redirect()->away($deskUrl);
    }
}
