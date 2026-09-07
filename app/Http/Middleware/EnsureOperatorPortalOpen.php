<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperatorPortalOpen
{
    /**
     * Block operator log in (and passkeys) while the marketing preview is up.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (PlatformSetting::current()->isOperatorPortalOpen()) {
            return $next($request);
        }

        if ($request->routeIs([
            'login.store',
            'passkey.login',
            'passkey.login-options',
            'two-factor.login',
            'two-factor.login.store',
        ])) {
            abort(403);
        }

        return $next($request);
    }
}
