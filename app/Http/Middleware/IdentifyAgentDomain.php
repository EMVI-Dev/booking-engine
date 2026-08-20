<?php

namespace App\Http\Middleware;

use App\Services\DomainResolverService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyAgentDomain
{
    public function __construct(
        protected DomainResolverService $domainResolver
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $agent = $this->domainResolver->resolveAgent($request);

        if ($agent) {
            // Bind current agent to request & service container
            $request->attributes->set('current_agent', $agent);
            app()->instance('current_agent', $agent);
        } else {
            $request->attributes->remove('current_agent');
            app()->forgetInstance('current_agent');
        }

        return $next($request);
    }
}
