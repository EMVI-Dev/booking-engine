<?php

namespace App\Http\Middleware;

use App\Models\Operator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PreventDemoIndexing
{
    public const ROBOTS_TAG = 'noindex, nofollow, noarchive, nosnippet';

    /**
     * Stamp demo storefront and demo-operator portal responses so crawlers skip them.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldBlockIndexing($request)) {
            $response->headers->set('X-Robots-Tag', self::ROBOTS_TAG);
        }

        return $response;
    }

    /**
     * Demo storefront hosts and demo-operator sessions must not be indexed.
     * Platform and admin pages stay indexable even when a demo operator exists.
     */
    private function shouldBlockIndexing(Request $request): bool
    {
        $operator = $request->attributes->get('current_operator');
        if ($operator instanceof Operator && $operator->isDemo()) {
            return true;
        }

        $user = $request->user();
        if ($user && (! $user->isAdmin() || session()->has('admin_impersonated_operator_id'))) {
            $portalOperator = $user->currentOperator();
            if ($portalOperator instanceof Operator && $portalOperator->isDemo()) {
                return true;
            }
        }

        $slug = (string) config('demo.slug', 'demo');

        return Str::startsWith(Str::lower($request->getHost()), $slug.'.');
    }
}
