<x-layouts::auth :title="__('Agent Log in')">
    <div class="flex flex-col gap-6">
        <div class="text-center space-y-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                {{ __('Agent Portal') }}
            </span>
            <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
                {{ __('Welcome back') }}
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Sign in to manage your tour packages, reservations, and availability.') }}
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <!-- Email Address -->
            <div>
                <x-label for="email" :value="__('Email address')" required />
                <x-input
                    id="email"
                    name="email"
                    :value="old('email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="agent@example.com"
                    :error="$errors->has('email')"
                />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <!-- Password -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <x-label for="password" :value="__('Password')" required />
                    @if (Route::has('password.request'))
                        <a class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium" href="{{ route('password.request') }}" wire:navigate>
                            {{ __('Forgot password?') }}
                        </a>
                    @endif
                </div>
                <x-input
                    id="password"
                    name="password"
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
                <x-checkbox name="remember" :label="__('Remember this device')" :checked="old('remember')" />
            </div>

            <div>
                <x-button variant="primary" type="submit" class="w-full shadow-sm font-semibold" data-test="login-button">
                    {{ __('Sign In to Storefront') }}
                </x-button>
            </div>
        </form>

        <div class="text-sm text-center text-zinc-600 dark:text-zinc-400">
            <span>{{ __('New tour operator or guide?') }}</span>
            <a href="{{ route('register') }}" class="font-semibold underline text-indigo-600 dark:text-indigo-400 hover:text-indigo-500" wire:navigate>{{ __('Create storefront') }}</a>
        </div>
    </div>
</x-layouts::auth>
