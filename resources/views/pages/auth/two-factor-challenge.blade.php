<x-layouts::auth :title="__('Two-factor authentication')">
    <div class="flex flex-col gap-6">
        <div
            class="relative w-full h-auto"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;
                    this.code = '';
                    this.recovery_code = '';
                },
            }"
        >
            <div x-show="!showRecoveryInput">
                <x-auth-header
                    :title="__('Authentication code')"
                    :description="__('Enter the authentication code provided by your authenticator application.')"
                />
            </div>

            <div x-show="showRecoveryInput">
                <x-auth-header
                    :title="__('Recovery code')"
                    :description="__('Please confirm access to your account by entering one of your emergency recovery codes.')"
                />
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}" class="flex flex-col gap-5 mt-4">
                @csrf

                <div x-show="!showRecoveryInput">
                    <x-label for="code" :value="__('Code')" required />
                    <x-input
                        id="code"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        autofocus
                        autocomplete="one-time-code"
                        placeholder="123456"
                        :error="$errors->has('code')"
                    />
                    <x-input-error :messages="$errors->get('code')" />
                </div>

                <div x-show="showRecoveryInput">
                    <x-label for="recovery_code" :value="__('Recovery Code')" required />
                    <x-input
                        id="recovery_code"
                        type="text"
                        name="recovery_code"
                        autocomplete="one-time-code"
                        :error="$errors->has('recovery_code')"
                    />
                    <x-input-error :messages="$errors->get('recovery_code')" />
                </div>

                <x-button
                    variant="primary"
                    type="submit"
                    class="w-full"
                >
                    {{ __('Continue') }}
                </x-button>

                <div class="text-sm text-center text-zinc-600 dark:text-zinc-400">
                    <button type="button" class="underline cursor-pointer hover:text-zinc-900 dark:hover:text-white" @click="toggleInput()">
                        <span x-show="!showRecoveryInput">{{ __('Login using a recovery code') }}</span>
                        <span x-show="showRecoveryInput">{{ __('Login using an authentication code') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::auth>
