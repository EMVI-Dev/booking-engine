<?php

namespace App\Http\Controllers\Auth;

use App\Models\Operator;
use App\Services\AuthHandoffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegistrationHandoffController
{
    /**
     * Finish registration on the operator slug host and open the desk.
     */
    public function __invoke(Request $request, AuthHandoffService $handoff): RedirectResponse
    {
        $user = $handoff->consume($request);

        if ($user === null || $user->isAdmin()) {
            abort(403, __('This sign-in link has expired or was already used. Please log in.'));
        }

        $operator = $request->attributes->get('current_operator');
        if (! $operator instanceof Operator || ! $user->operators()->whereKey($operator->id)->exists()) {
            abort(403, __('This sign-in link does not belong to this tour shop.'));
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        session()->flash('welcome_onboarding', true);
        session()->flash('status', __('Your account is created. Finish the setup list above to take bookings.'));

        return redirect()->route('dashboard');
    }
}
