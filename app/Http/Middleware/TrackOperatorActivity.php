<?php

namespace App\Http\Middleware;

use App\Models\Operator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackOperatorActivity
{
    /**
     * Handle an incoming request.
     *
     * Automatically update last_active_at for authenticated operators.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user) {
            // Platform admins (browsing or managing a shop) never count as operator activity
            if ($user->isAdmin()) {
                return $response;
            }

            /** @var Operator|null $operator */
            $operator = $request->attributes->get('current_operator') ?? $user->currentOperator();

            if ($operator && ! $operator->is_demo) {
                $operator->touchActivity();
            }
        }

        return $response;
    }
}
