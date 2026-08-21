<?php

namespace App\Http\Middleware;

use App\Services\DomainResolverService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyOperatorDomain
{
    public function __construct(
        protected DomainResolverService $domainResolver
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $operator = $this->domainResolver->resolveOperator($request);

        if ($operator) {
            // Bind current operator to request & service container
            $request->attributes->set('current_operator', $operator);
            $request->attributes->set('current_agent', $operator);
            app()->instance('current_operator', $operator);
            app()->instance('current_agent', $operator);
        } else {
            $request->attributes->remove('current_operator');
            $request->attributes->remove('current_agent');
            app()->forgetInstance('current_operator');
            app()->forgetInstance('current_agent');
        }

        return $next($request);
    }
}
