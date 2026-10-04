<?php

namespace App\Http\Middleware;

use App\Enums\DomainType;
use App\Models\OperatorDomain;
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
            if ($redirect = $this->redirectFromInactiveCustomDomain($request)) {
                return $redirect;
            }

            $request->attributes->remove('current_operator');
            $request->attributes->remove('current_agent');
            app()->forgetInstance('current_operator');
            app()->forgetInstance('current_agent');
        }

        return $next($request);
    }

    /**
     * A custom domain that is no longer live (plan downgrade, failed check) still points here:
     * send guests to the same page on the operator's slug address instead of the platform site.
     */
    private function redirectFromInactiveCustomDomain(Request $request): ?Response
    {
        $host = strtolower($request->getHost());

        if ($this->domainResolver->isPlatformHost($host)) {
            return null;
        }

        $operator = OperatorDomain::query()
            ->where('domain', $host)
            ->where('type', DomainType::Custom)
            ->with('operator')
            ->first()
            ?->operator;

        if (! $operator) {
            return null;
        }

        return redirect()->away($operator->slugDeskRoot().$request->getRequestUri(), 302);
    }
}
