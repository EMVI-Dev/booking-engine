<?php

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $passwordUpdated = false;

    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;

    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->passwordUpdated = true;

        $this->dispatch('password-updated');
    }

    /**
     * Load the user's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at' => $passkey->created_at->toISOString(),
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at' => $passkey->last_used_at?->toISOString(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Open the delete passkey modal for the given passkey.
     */
    public function confirmDelete(int $id): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($id);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey currently pending deletion.
     */
    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(auth()->user(), $passkey);

        $this->showDeleteModal = false;
        $this->reset('deletingPasskeyId', 'deletingPasskeyName');
        $this->loadPasskeys();
    }

    /**
     * Close the delete passkey modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->reset('deletingPasskeyId', 'deletingPasskeyName');
    }

    /**
     * Handle the passkey registered event.
     */
    #[On('passkey-registered')]
    public function onPasskeyRegistered(): void
    {
        $this->loadPasskeys();
    }

    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <div class="space-y-6">
            <!-- Card 1: Password Update -->
            <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-xs">
                        <i class="fa-solid fa-key"></i>
                    </span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('Update password') }}
                    </h3>
                </div>

                <form method="POST" wire:submit="updatePassword" class="space-y-4">
                    <div>
                        <x-label for="current_password" :value="__('Current password')" required />
                        <x-input
                            id="current_password"
                            wire:model="current_password"
                            type="password"
                            required
                            autocomplete="current-password"
                            :error="$errors->has('current_password')"
                        />
                        <x-input-error :messages="$errors->get('current_password')" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-label for="password" :value="__('New password')" required />
                            <x-input
                                id="password"
                                wire:model="password"
                                type="password"
                                required
                                autocomplete="new-password"
                                :error="$errors->has('password')"
                            />
                            <x-input-error :messages="$errors->get('password')" />
                        </div>

                        <div>
                            <x-label for="password_confirmation" :value="__('Confirm password')" required />
                            <x-input
                                id="password_confirmation"
                                wire:model="password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                :error="$errors->has('password_confirmation')"
                            />
                            <x-input-error :messages="$errors->get('password_confirmation')" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4 pt-2">
                        <x-button variant="primary" type="submit" data-test="update-password-button" class="shadow-sm">
                            <i class="fa-solid fa-floppy-disk mr-1 text-xs"></i>
                            {{ __('Update password') }}
                        </x-button>

                        <div x-data="{ shown: false, timeout: null }"
                             x-init="@this.on('password-updated', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2000); })"
                             x-show.transition.out.opacity.duration.1500ms="shown"
                             x-transition:leave.opacity.duration.1500ms
                             style="display: none;"
                             class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            <i class="fa-solid fa-circle-check"></i>
                            {{ __('Password updated successfully.') }}
                        </div>
                    </div>
                </form>
            </div>

            <!-- Card 2: Two-Factor Authentication -->
            @if ($canManageTwoFactor)
                <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                        <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/70 text-sky-600 dark:text-sky-400 text-xs">
                            <i class="fa-solid fa-shield-halved"></i>
                        </span>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Two-factor authentication') }}
                        </h3>
                    </div>

                    <div class="flex flex-col w-full space-y-4 text-xs" wire:cloak>
                        @if ($twoFactorEnabled)
                            <div class="space-y-4">
                                <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                                    {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                                </p>

                                <div class="flex justify-start">
                                    <x-button
                                        variant="danger"
                                        wire:click="disable"
                                        size="sm"
                                    >
                                        <i class="fa-solid fa-lock-open mr-1 text-xs"></i>
                                        {{ __('Disable 2FA') }}
                                    </x-button>
                                </div>

                                <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                            </div>
                        @else
                            <div class="space-y-4">
                                <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed">
                                    {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                                </p>

                                <x-button
                                    variant="primary"
                                    size="sm"
                                    x-data=""
                                    x-on:click="$dispatch('open-modal', 'two-factor-setup-modal'); $wire.dispatch('start-two-factor-setup');"
                                >
                                    <i class="fa-solid fa-shield mr-1 text-xs"></i>
                                    {{ __('Enable 2FA') }}
                                </x-button>

                                <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Card 3: Passkeys -->
            @if ($canManagePasskeys)
                <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                    <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                        <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 text-xs">
                            <i class="fa-solid fa-fingerprint"></i>
                        </span>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ __('Passkeys') }}
                        </h3>
                    </div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 -mt-2">{{ __('Manage your passkeys for passwordless sign-in') }}</p>

                    <div class="flex flex-col w-full space-y-4 text-xs" wire:cloak>
                        <div class="border rounded-2xl border-slate-200 dark:border-zinc-800 overflow-hidden divide-y divide-slate-100 dark:divide-zinc-800">
                            @forelse ($passkeys as $passkey)
                                <div class="flex items-center justify-between p-4 bg-white dark:bg-zinc-900">
                                    <div class="flex items-center gap-3.5">
                                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400">
                                            <i class="fa-solid fa-fingerprint text-base"></i>
                                        </div>
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-2">
                                                <p class="font-bold text-slate-900 dark:text-white">{{ $passkey['name'] }}</p>
                                                @if ($passkey['authenticator'])
                                                    <span class="text-[10px] px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800 font-semibold">{{ $passkey['authenticator'] }}</span>
                                                @endif
                                            </div>
                                            <p class="text-slate-400 text-[11px]">
                                                {{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}
                                                @if ($passkey['last_used_at_diff'])
                                                    <span class="opacity-50 mx-1">&bull;</span>
                                                    {{ __('Last used :time', ['time' => $passkey['last_used_at_diff']]) }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <x-button
                                        variant="ghost"
                                        size="xs"
                                        wire:click="confirmDelete({{ $passkey['id'] }})"
                                        class="text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50"
                                    >
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </x-button>
                                </div>
                            @empty
                                <div class="p-8 text-center bg-white dark:bg-zinc-900">
                                    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('No passkeys yet') }}</p>
                                    <p class="text-xs text-zinc-500 mt-1">{{ __('Add a passkey to sign in without a password') }}</p>
                                </div>
                            @endforelse
                        </div>

                        <x-passkey-registration />
                    </div>
                </div>
            @endif
        </div>
    </x-pages::settings.layout>

    <div x-data="{ open: @entangle('showDeleteModal') }">
        <x-modal name="delete-passkey-modal" :show="$showDeleteModal" maxWidth="md">
            <div class="p-6 space-y-4">
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Remove passkey') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
                    </p>
                </div>

                <div class="flex justify-end gap-2.5 pt-2">
                    <x-button variant="outline" size="sm" wire:click="closeDeleteModal">
                        {{ __('Cancel') }}
                    </x-button>
                    <x-button variant="danger" size="sm" wire:click="deletePasskey">
                        {{ __('Remove passkey') }}
                    </x-button>
                </div>
            </div>
        </x-modal>
    </div>
</section>
