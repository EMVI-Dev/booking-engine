<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStorefrontTransactionsAllowed
{
    /**
     * Block guest checkout, pay, and cancel while platform maintenance is on.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        PlatformSetting::current()->assertStorefrontTransactionsAllowed();

        return $next($request);
    }
}
