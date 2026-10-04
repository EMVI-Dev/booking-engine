<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Services\AuthHandoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Send a new operator to their slug desk host after registration.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        $user = $request->user();
        $operator = $user instanceof User ? $user->currentOperator() : null;

        if ($operator === null || blank($operator->slug)) {
            return redirect()->intended(Fortify::redirects('register'));
        }

        $deskUrl = app(AuthHandoffService::class)->url('auth.registration-handoff', $user, $operator);

        return redirect()->away($deskUrl);
    }
}
