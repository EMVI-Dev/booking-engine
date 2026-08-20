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
    <div class="p-6 space-y-6">
        <div class="text-center space-y-2">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->modalConfig['title'] }}</h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->modalConfig['description'] }}</p>
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
                    <x-button variant="outline" class="flex-1" wire:click="resetVerification">
                        {{ __('Back') }}
                    </x-button>
                    <x-button variant="primary" class="flex-1" wire:click="confirmTwoFactor">
                        {{ __('Confirm') }}
                    </x-button>
                </div>
            </div>
        @else
            @error('setupData')
                <div class="p-3 bg-red-50 dark:bg-red-950/50 text-red-700 dark:text-red-400 rounded-lg text-sm">
                    {{ $message }}
                </div>
            @enderror

            <div class="flex justify-center">
                <div class="w-48 h-48 border rounded-xl border-zinc-200 dark:border-zinc-700 flex items-center justify-center p-3 bg-white">
                    @empty($qrCodeSvg)
                        <div class="animate-pulse text-zinc-400 text-sm">
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
                    class="w-full"
                    wire:click="showVerificationIfNecessary"
                >
                    {{ $this->modalConfig['buttonText'] }}
                </x-button>
            </div>

            @if ($manualSetupKey)
                <div class="space-y-2">
                    <p class="text-xs text-zinc-500 text-center">{{ __('Or enter the setup key manually:') }}</p>
                    <div class="p-2.5 bg-zinc-100 dark:bg-zinc-800 rounded-lg text-center font-mono text-xs select-all text-zinc-800 dark:text-zinc-200">
                        {{ $manualSetupKey }}
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-modal>
