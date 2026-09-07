<?php

namespace App\Http\Middleware;

use App\Services\DomainResolverService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnPlatformDomain
{
    public function __construct(
        protected DomainResolverService $domainResolver
    ) {}

    /**
     * Keep platform-only routes off operator slugs and custom domains.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->domainResolver->isPlatformRoot($request->getHost())) {
            abort(404);
        }

        return $next($request);
    }
}
