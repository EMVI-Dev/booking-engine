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
    class="p-4 sm:p-5 space-y-4 border rounded-[8px] border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#10141d]"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="space-y-1">
        <h4 class="text-xs sm:text-sm font-semibold text-[#12181E] dark:text-[#F4F5F7]">{{ __('2FA recovery codes') }}</h4>
        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </p>
    </div>

    <div>
        <div class="flex flex-wrap items-center gap-2.5">
            <x-button
                x-show="!showRecoveryCodes"
                variant="outline"
                size="sm"
                class="rounded-[6px]"
                @click="showRecoveryCodes = true;"
            >
                {{ __('View recovery codes') }}
            </x-button>

            <x-button
                x-show="showRecoveryCodes"
                variant="outline"
                size="sm"
                class="rounded-[6px]"
                @click="showRecoveryCodes = false"
            >
                {{ __('Hide recovery codes') }}
            </x-button>

            @if (filled($recoveryCodes))
                <x-button
                    x-show="showRecoveryCodes"
                    variant="ghost"
                    size="sm"
                    class="rounded-[6px]"
                    wire:click="regenerateRecoveryCodes"
                >
                    {{ __('Regenerate codes') }}
                </x-button>
            @endif
        </div>

        <div
            x-show="showRecoveryCodes"
            x-transition
            class="mt-4 space-y-2.5"
            style="display: none;"
        >
            @if (filled($recoveryCodes))
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3 font-mono text-xs rounded-[6px] bg-[#F4F5F7] dark:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#12181E] dark:text-[#F4F5F7]">
                    @foreach($recoveryCodes as $code)
                        <div class="select-text py-1 px-1.5">
                            {{ $code }}
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                    {{ __('Each recovery code can be used once to access your account and will be removed after use.') }}
                </p>
            @endif
        </div>
    </div>
</div>
