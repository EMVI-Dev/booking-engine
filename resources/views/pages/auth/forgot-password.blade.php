<x-layouts::auth :title="__('Forgot password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
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
                    placeholder="email@example.com"
                    :error="$errors->has('email')"
                />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <x-button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('Email password reset link') }}
            </x-button>
        </form>

        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Or, return to') }}</span>
            <a href="{{ route('login') }}" class="font-medium underline hover:text-zinc-900 dark:hover:text-white" wire:navigate>{{ __('log in') }}</a>
        </div>
    </div>
</x-layouts::auth>
