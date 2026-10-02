<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showVerificationStep = false;

    public bool $setupComplete = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    /**
     * Mount the component.
     */
    public function mount(bool $requiresConfirmation): void
    {
        $this->requiresConfirmation = $requiresConfirmation;
    }

    #[On('start-two-factor-setup')]
    public function startTwoFactorSetup(): void
    {
        $enableTwoFactorAuthentication = app(EnableTwoFactorAuthentication::class);
        $enableTwoFactorAuthentication(auth()->user());

        $this->loadSetupData();
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = auth()->user()?->fresh();

        try {
            if (! $user || ! $user->two_factor_secret) {
                throw new Exception('Two-factor setup secret is not available.');
            }

            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
        $this->dispatch('two-factor-enabled');
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->setupComplete = true;

        $this->closeModal();

        $this->dispatch('two-factor-enabled');
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showVerificationStep',
            'setupComplete',
        );

        $this->resetErrorBag();
    }

    /**
     * Get the current modal configuration state.
     */
    #[Computed]
    public function modalConfig(): array
    {
        if ($this->setupComplete) {
            return [
                'title' => __('Two-factor authentication enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify authentication code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable two-factor authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }
}; ?>

<x-modal name="two-factor-setup-modal" maxWidth="md">
    <div class="p-5 sm:p-6 space-y-5 rounded-t-[16px] sm:rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433]">
        <div class="mx-auto -mt-2 mb-2 h-1 w-10 shrink-0 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

        <div class="text-center space-y-1.5">
            <h3 class="text-sm sm:text-base font-semibold text-[#12181E] dark:text-white">{{ $this->modalConfig['title'] }}</h3>
            <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">{{ $this->modalConfig['description'] }}</p>
        </div>

        @if ($showVerificationStep)
            <div class="space-y-4">
                <div>
                    <x-label for="otp-code" :value="__('Authentication code')" required />
                    <x-input
                        id="otp-code"
                        wire:model="code"
                        type="text"
                        maxlength="6"
                        placeholder="123456"
                        autofocus
                        :error="$errors->has('code')"
                    />
                    <x-input-error :messages="$errors->get('code')" />
                </div>

                <div class="flex items-center gap-3">
                    <x-button variant="outline" class="flex-1 rounded-[6px]" wire:click="resetVerification">
                        {{ __('Back') }}
                    </x-button>
                    <x-button variant="primary" class="flex-1 rounded-[6px]" wire:click="confirmTwoFactor">
                        {{ __('Confirm') }}
                    </x-button>
                </div>
            </div>
        @else
            @error('setupData')
                <div class="p-3 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-900 rounded-[8px] text-xs font-medium">
                    {{ $message }}
                </div>
            @enderror

            <div class="flex justify-center">
                <div class="w-48 h-48 border rounded-[8px] border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-center p-3 bg-white">
                    @empty($qrCodeSvg)
                        <div class="animate-pulse text-[#5A6578] dark:text-[#9DA4B2] text-xs">
                            {{ __('Loading QR...') }}
                        </div>
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            {!! $qrCodeSvg !!}
                        </div>
                    @endempty
                </div>
            </div>

            <div>
                <x-button
                    :disabled="$errors->has('setupData')"
                    variant="primary"
                    class="w-full rounded-[6px]"
                    wire:click="showVerificationIfNecessary"
                >
                    {{ $this->modalConfig['buttonText'] }}
                </x-button>
            </div>

            @if ($manualSetupKey)
                <div class="space-y-1.5">
                    <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] text-center">{{ __('Or enter the setup key manually:') }}</p>
                    <div class="p-2.5 bg-[#F4F5F7] dark:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] rounded-[6px] text-center font-mono text-xs select-all text-[#12181E] dark:text-[#F4F5F7]">
                        {{ $manualSetupKey }}
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-modal>
