<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify's sign-up and password-reset POST routes come with no rate limit and cannot take
 * one through config. This applies the `auth-forms` limiter (FortifyServiceProvider) to
 * them, which also works with cached routes.
 */
class ThrottleAuthForms
{
    public const ROUTES = ['register.store', 'password.email', 'password.update'];

    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && in_array($request->route()?->getName(), self::ROUTES, true)) {
            return $this->throttle->handle($request, $next, 'auth-forms');
        }

        return $next($request);
    }
}
