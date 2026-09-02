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

        $this->redirectIntended(route('admin.dashboard'), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="text-center space-y-2">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30">
            <i class="fa-brands fa-searchengin text-sm"></i>
            {{ __('Platform Master Control') }}
        </span>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
            {{ __('Platform Admin Login') }}
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400">
            {{ __('Authenticate to manage central DOKU credentials, platform take-rates, and global operations.') }}
        </p>
    </div>

    <!-- Quick Credentials Helper Badge for Convenience -->
    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-[#141821] border border-slate-200/80 dark:border-[#1e2433] text-xs flex items-center justify-between gap-2">
        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
            <i class="fa-solid fa-key text-[#8a7808] dark:text-[#FFEF4D]"></i>
            <span class="font-mono text-[11px]">admin@emvi.dev / password</span>
        </div>
        <button
            type="button"
            x-on:click="$wire.set('email', 'admin@emvi.dev'); $wire.set('password', 'password');"
            class="px-2.5 py-1 rounded-lg bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-[10px] uppercase tracking-wider transition cursor-pointer"
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
            <button type="submit" class="w-full h-11 rounded-2xl font-black text-xs bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] shadow-xs inline-flex items-center justify-center gap-2 cursor-pointer transition" data-test="admin-login-button">
                <i class="fa-solid fa-lock-open text-xs"></i>
                {{ __('Access Platform Dashboard') }}
            </button>
        </div>
    </form>

    <div class="text-xs text-center text-slate-500 dark:text-zinc-400 border-t border-slate-200/80 dark:border-[#1e2433] pt-4">
        <span>{{ __('Looking for your tour operator portal?') }}</span>
        <a href="{{ route('login') }}" class="font-bold underline text-[#8a7808] dark:text-[#FFEF4D] hover:opacity-80 ml-1" wire:navigate>{{ __('Operator Sign In') }}</a>
    </div>
</div>
