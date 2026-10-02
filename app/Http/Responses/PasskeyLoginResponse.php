<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function __construct(
        protected LoginResponse $loginResponse
    ) {}

    /**
     * Send an operator to their slug desk host after passkey login.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        $redirectResponse = $this->loginResponse->resolveRedirect($request);
        $targetUrl = $redirectResponse->getTargetUrl();

        if ($request->wantsJson()) {
            return new JsonResponse([
                'redirect' => $targetUrl,
            ], 200);
        }

        return redirect()->away($targetUrl);
    }
}
