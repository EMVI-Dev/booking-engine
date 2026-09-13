<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegistrationHandoffController
{
    /**
     * Finish registration on the operator slug host and open the desk.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = User::query()->findOrFail($request->query('user'));

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        session()->flash('welcome_onboarding', true);
        session()->flash('status', __('Your account is created. Finish the setup list above to take bookings.'));

        return redirect()->route('dashboard');
    }
}
