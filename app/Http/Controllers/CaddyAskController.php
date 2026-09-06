<?php

namespace App\Http\Controllers;

use App\Services\DomainResolverService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CaddyAskController extends Controller
{
    /**
     * Caddy on-demand TLS gate. 200 allows a Let's Encrypt padlock.
     */
    public function __invoke(Request $request, DomainResolverService $domains): Response
    {
        $expected = (string) config('domains.caddy_ask_token', '');
        $provided = (string) $request->query('token', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response('', 403);
        }

        $host = (string) $request->query('domain', '');

        if (! $domains->customDomainMayReceiveCertificate($host)) {
            return response('', 404);
        }

        return response('', 200);
    }
}
