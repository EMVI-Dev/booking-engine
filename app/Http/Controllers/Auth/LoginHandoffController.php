<?php

namespace App\Http\Controllers\Auth;

use App\Models\Operator;
use App\Services\AuthHandoffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginHandoffController
{
    /**
     * Finish login on the operator slug host and open the desk.
     */
    public function __invoke(Request $request, AuthHandoffService $handoff): RedirectResponse
    {
        $user = $handoff->consume($request);

        if ($user === null || $user->isAdmin()) {
            abort(403, __('This sign-in link has expired or was already used. Please log in.'));
        }

        $operator = $request->attributes->get('current_operator');
        if ($operator instanceof Operator && ! $user->operators()->whereKey($operator->id)->exists()) {
            abort(403, __('These credentials do not match this tour shop.'));
        }

        Auth::login($user, remember: $request->boolean('remember'));
        $request->session()->regenerate();

        $intended = $request->query('intended');
        if (is_string($intended) && str_starts_with($intended, '/') && ! str_starts_with($intended, '//') && $intended !== '/login') {
            return redirect()->to($intended);
        }

        return redirect()->route('dashboard');
    }
}
