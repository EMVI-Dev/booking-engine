<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin sign in')] #[Layout('layouts.auth')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * Throttle key scoped to email + IP address (5 attempts / minute).
     */
    private function throttleKey(): string
    {
        return Str::lower($this->email).'|'.request()->ip();
    }

    /**
     * Authenticate platform administrator.
     */
    public function login(): void
    {
        $validated = $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (RateLimiter::tooManyAttempts('admin-login:'.$this->throttleKey(), 5)) {
            $seconds = RateLimiter::availableIn('admin-login:'.$this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $provider = Auth::getProvider();
        /** @var User|null $user */
        $user = $provider->retrieveByCredentials(['email' => $validated['email']]);

        // One answer for "wrong password" and "not an admin" so this form never confirms an operator's password.
        if (! $user || ! $provider->validateCredentials($user, ['password' => $validated['password']]) || ! $user->isAdmin()) {
            RateLimiter::hit('admin-login:'.$this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our platform records.'),
            ]);
        }

        RateLimiter::clear('admin-login:'.$this->throttleKey());

        // Admins with two-factor enabled must pass Fortify's challenge before a session exists.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $this->remember,
            ]);

            $this->redirectRoute('two-factor.login');

            return;
        }

        Auth::login($user, $this->remember);
        session()->regenerate();

        app(\App\Services\AdminAuditLogger::class)->record('admin.login', $user);

        $this->redirectIntended(route('admin.dashboard'), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="text-center space-y-2">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] text-xs font-medium bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
            <i class="fa-brands fa-searchengin text-sm"></i>
            {{ __('Admin') }}
        </span>
        <h1 class="text-[20px] font-medium leading-[1.6] text-[#1C2024] dark:text-white">
            {{ __('Admin sign in') }}
        </h1>
        <p class="text-xs text-[#60646C] dark:text-zinc-400">
            {{ __('For the people who run EMVI — operators, money, and guest checkout.') }}
        </p>
    </div>

    <form wire:submit="login" class="flex flex-col gap-4">
        <!-- Email Address -->
        <div>
            <label for="email" class="block text-xs font-medium text-[#1C2024] dark:text-white mb-1.5">
                {{ __('Admin Email') }} <span class="text-rose-500">*</span>
            </label>
            <input
                id="email"
                wire:model="email"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="{{ __('you@email.com') }}"
                class="w-full px-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-xs font-medium text-[#1C2024] dark:text-white mb-1.5">
                {{ __('Master Password') }} <span class="text-rose-500">*</span>
            </label>
            <input
                id="password"
                wire:model="password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="w-full px-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input
                    type="checkbox"
                    wire:model="remember"
                    class="rounded-[4px] border-[#E4E5E9] dark:border-[#1E2433] text-[#FFEF4D] focus:ring-0 shadow-none"
                />
                <span class="text-xs text-[#60646C] dark:text-zinc-400">{{ __('Remember admin session') }}</span>
            </label>
        </div>

        <div class="pt-1">
            <button
                type="submit"
                class="w-full h-9 rounded-[6px] font-medium text-xs bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] shadow-none inline-flex items-center justify-center gap-2 cursor-pointer transition"
                data-test="admin-login-button"
            >
                <i class="fa-solid fa-lock-open text-xs"></i>
                <span>{{ __('Access Platform Dashboard') }}</span>
            </button>
        </div>
    </form>

    <div class="text-xs text-center text-[#60646C] dark:text-zinc-400 border-t border-[#E4E5E9] dark:border-[#1E2433] pt-4">
        <span>{{ __('Looking for your tour operator portal?') }}</span>
        <a href="{{ route('login') }}" class="font-medium underline text-[#856404] dark:text-[#FFEF4D] hover:opacity-80 ml-1" wire:navigate>{{ __('Operator Sign In') }}</a>
    </div>
</div>
