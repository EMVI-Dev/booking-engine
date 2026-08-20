<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Platform Admin Login')] #[Layout('layouts.auth')] class extends Component {
    public string $email = 'admin@emvi.dev';
    public string $password = '';
    public bool $remember = true;

    /**
     * Authenticate platform administrator.
     */
    public function login(): void
    {
        $validated = $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our platform records.'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isAdmin()) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('Access denied. This login portal is strictly reserved for platform administrators.'),
            ]);
        }

        session()->regenerate();

        $this->redirectIntended(route('admin.platform.edit'), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="text-center space-y-2">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800 dark:bg-purple-950/80 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
            <i class="fa-brands fa-searchengin text-sm text-purple-600 dark:text-purple-400"></i>
            {{ __('Platform Master Control') }}
        </span>
        <h1 class="text-2xl font-black tracking-tight text-zinc-900 dark:text-white">
            {{ __('Platform Admin Login') }}
        </h1>
        <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Authenticate to manage central DOKU credentials, platform take-rates, and global operations.') }}
        </p>
    </div>

    <!-- Quick Credentials Helper Badge for Convenience -->
    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800 border border-slate-200/80 dark:border-zinc-800 text-xs flex items-center justify-between gap-2">
        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
            <i class="fa-solid fa-key text-purple-500"></i>
            <span class="font-mono text-[11px]">admin@emvi.dev / password</span>
        </div>
        <button
            type="button"
            x-on:click="$wire.set('email', 'admin@emvi.dev'); $wire.set('password', 'password');"
            class="px-2.5 py-1 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold text-[10px] uppercase tracking-wider transition cursor-pointer"
        >
            {{ __('Auto-fill') }}
        </button>
    </div>

    <form wire:submit="login" class="flex flex-col gap-5">
        <!-- Email Address -->
        <div>
            <x-label for="email" :value="__('Admin Email')" required />
            <x-input
                id="email"
                wire:model="email"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="admin@emvi.dev"
                :error="$errors->has('email')"
            />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <!-- Password -->
        <div>
            <x-label for="password" :value="__('Master Password')" required />
            <x-input
                id="password"
                wire:model="password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                :error="$errors->has('password')"
            />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <x-checkbox
                id="remember"
                wire:model="remember"
                :label="__('Remember admin session')"
            />
        </div>

        <div>
            <x-button variant="primary" type="submit" class="w-full shadow-sm font-bold bg-purple-600 hover:bg-purple-700 active:bg-purple-800 text-white" data-test="admin-login-button">
                <i class="fa-solid fa-lock-open mr-1.5 text-xs"></i>
                {{ __('Access Platform Dashboard') }}
            </x-button>
        </div>
    </form>

    <div class="text-xs text-center text-zinc-500 dark:text-zinc-400 border-t border-slate-200/80 dark:border-zinc-800 pt-4">
        <span>{{ __('Looking for your travel agent account?') }}</span>
        <a href="{{ route('login') }}" class="font-semibold underline text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 ml-1" wire:navigate>{{ __('Agent Sign In') }}</a>
    </div>
</div>
