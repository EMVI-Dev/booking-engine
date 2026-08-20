<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div
    class="py-5 space-y-4 border rounded-xl border-zinc-200 dark:border-zinc-800 p-5"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="space-y-1">
        <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('2FA recovery codes') }}</h4>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </p>
    </div>

    <div>
        <div class="flex flex-wrap items-center gap-3">
            <x-button
                x-show="!showRecoveryCodes"
                variant="outline"
                size="sm"
                @click="showRecoveryCodes = true;"
            >
                {{ __('View recovery codes') }}
            </x-button>

            <x-button
                x-show="showRecoveryCodes"
                variant="outline"
                size="sm"
                @click="showRecoveryCodes = false"
            >
                {{ __('Hide recovery codes') }}
            </x-button>

            @if (filled($recoveryCodes))
                <x-button
                    x-show="showRecoveryCodes"
                    variant="ghost"
                    size="sm"
                    wire:click="regenerateRecoveryCodes"
                >
                    {{ __('Regenerate codes') }}
                </x-button>
            @endif
        </div>

        <div
            x-show="showRecoveryCodes"
            x-transition
            class="mt-4 space-y-3"
            style="display: none;"
        >
            @if (filled($recoveryCodes))
                <div class="grid grid-cols-2 gap-2 p-3 font-mono text-xs rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">
                    @foreach($recoveryCodes as $code)
                        <div class="select-text py-1">
                            {{ $code }}
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-zinc-500">
                    {{ __('Each recovery code can be used once to access your account and will be removed after use.') }}
                </p>
            @endif
        </div>
    </div>
</div>
