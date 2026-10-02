<?php

use App\Concerns\RecordsAdminActions;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Your profile')] #[Layout('layouts.admin')] class extends Component {
    use RecordsAdminActions;

    use PasswordValidationRules;
    use ProfileValidationRules;

    // Profile Details
    public string $name = '';
    public string $email = '';
    public bool $profileSaved = false;

    // Password Update
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $passwordSaved = false;

    // 2FA Security
    public bool $canManageTwoFactor = false;
    public bool $twoFactorEnabled = false;
    public bool $requiresConfirmation = false;

    // Passkeys
    #[Locked]
    public bool $canManagePasskeys = false;

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
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;

        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null($user->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication($user);
            }

            $this->twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
    }

    /**
     * Update the admin profile information.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $previousEmail = $user->email;
        $user->fill($validated);
        $user->save();

        if ($previousEmail !== $user->email) {
            $this->audit('admin.email_changed', $user, ['from' => $previousEmail, 'to' => $user->email]);
        }

        $this->profileSaved = true;
        session()->flash('success_profile', __('Admin profile details updated successfully.'));
    }

    /**
     * Update the admin password.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);
        $this->audit('admin.password_changed', Auth::user());

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->passwordSaved = true;
        session()->flash('success_password', __('Admin credentials updated successfully.'));
    }

    /**
     * Load the admin's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = Auth::user()->passkeys()
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
     * Open the delete passkey modal.
     */
    public function confirmDelete(int $id): void
    {
        $passkey = Auth::user()->passkeys()->findOrFail($id);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey pending deletion.
     */
    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        $passkey = Auth::user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(Auth::user(), $passkey);
        $this->audit('admin.passkey_removed', Auth::user(), ['name' => $passkey->name]);

        $this->showDeleteModal = false;
        $this->reset('deletingPasskeyId', 'deletingPasskeyName');
        $this->loadPasskeys();
        session()->flash('success_passkey', __('Passkey removed successfully.'));
    }

    /**
     * Close the delete passkey modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->reset('deletingPasskeyId', 'deletingPasskeyName');
    }

    #[On('passkey-registered')]
    public function onPasskeyRegistered(): void
    {
        $this->loadPasskeys();
        session()->flash('success_passkey', __('Passkey registered successfully!'));
    }

    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
        session()->flash('success_2fa', __('Two-factor authentication enabled successfully!'));
    }

    /**
     * Disable two-factor authentication.
     */
    public function disableTwoFactor(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(Auth::user());
        $this->audit('admin.two_factor_disabled', Auth::user());
        $this->twoFactorEnabled = false;
        session()->flash('success_2fa', __('Two-factor authentication has been disabled.'));
    }
}; ?>

<div class="space-y-6 w-full">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E4E5E9] dark:border-[#1E2433]">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-[#FFEF4D] text-[#12181E] text-sm">
                <i class="fa-solid fa-user-shield"></i>
            </span>
            <div>
                <h1 class="text-[20px] font-medium leading-[1.6] text-[#1C2024] dark:text-white">
                    {{ __('Your profile') }}
                </h1>
                <p class="text-[13px] text-[#60646C] dark:text-zinc-400">
                    {{ __('Name, password, and extra sign-in protection.') }}
                </p>
            </div>
        </div>
        <div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 text-xs font-medium">
                <i class="fa-solid fa-crown text-[10px]"></i>
                <span>{{ __('Admin') }}</span>
            </span>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Account, Password, 2FA, Passkeys (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- 1. Administrative Identity Card -->
            <div class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <div class="flex items-center gap-2.5">
                        <span class="p-1.5 rounded-[6px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 text-xs">
                            <i class="fa-solid fa-id-card"></i>
                        </span>
                        <div>
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-[#1C2024] dark:text-white">
                                {{ __('Your details') }}
                            </h2>
                            <p class="text-[11px] text-[#60646C] dark:text-zinc-400">
                                {{ __('The name and email for this admin account.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <form wire:submit="updateProfileInformation" class="space-y-4">
                    <div>
                        <label for="admin_name" class="block text-xs font-medium text-[#1C2024] dark:text-white mb-1.5">
                            {{ __('Full Name') }} <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="admin_name"
                            type="text"
                            wire:model="name"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="{{ __('Platform Administrator') }}"
                            class="w-full px-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
                        />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <label for="admin_email" class="block text-xs font-medium text-[#1C2024] dark:text-white mb-1.5">
                            {{ __('Admin Email Address') }} <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="admin_email"
                            type="email"
                            wire:model="email"
                            required
                            autocomplete="email"
                            placeholder="admin@travelengine.id"
                            class="w-full px-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
                        />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        @if (session('success_profile'))
                            <div class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>{{ session('success_profile') }}</span>
                            </div>
                        @else
                            <div></div>
                        @endif

                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-xs font-medium cursor-pointer transition shadow-none"
                        >
                            <i class="fa-solid fa-floppy-disk text-xs"></i>
                            <span>{{ __('Save') }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Appearance & Theme Mode Card -->
            <div class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <div class="flex items-center gap-2.5">
                        <span class="p-1.5 rounded-[6px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 text-xs">
                            <i class="fa-solid fa-circle-half-stroke"></i>
                        </span>
                        <div>
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-[#1C2024] dark:text-white">
                                {{ __('Appearance') }}
                            </h2>
                            <p class="text-[11px] text-[#60646C] dark:text-zinc-400">
                                {{ __('Light, dark, or match the computer.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" x-data="{
                    theme: localStorage.getItem('theme') || 'dark',
                    setTheme(val) {
                        this.theme = val;
                        localStorage.setItem('theme', val);
                        if (val === 'dark') {
                            document.documentElement.classList.add('dark');
                        } else if (val === 'light') {
                            document.documentElement.classList.remove('dark');
                        } else {
                            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                                document.documentElement.classList.add('dark');
                            } else {
                                document.documentElement.classList.remove('dark');
                            }
                        }
                    }
                }">
                    <button
                        type="button"
                        @click="setTheme('light')"
                        :class="theme === 'light' ? 'border-[#FFEF4D] bg-[#FFEF4D]/10' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]'"
                        class="flex flex-col items-center gap-2 p-3.5 rounded-[8px] border text-xs font-medium cursor-pointer transition shadow-none"
                    >
                        <div class="w-7 h-7 rounded-[6px] bg-amber-50 dark:bg-amber-950/60 text-amber-600 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-sun"></i>
                        </div>
                        <span class="text-[#1C2024] dark:text-white">{{ __('Light Theme') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="setTheme('dark')"
                        :class="theme === 'dark' ? 'border-[#FFEF4D] bg-[#FFEF4D]/10' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]'"
                        class="flex flex-col items-center gap-2 p-3.5 rounded-[8px] border text-xs font-medium cursor-pointer transition shadow-none"
                    >
                        <div class="w-7 h-7 rounded-[6px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] flex items-center justify-center text-sm">
                            <i class="fa-solid fa-moon"></i>
                        </div>
                        <span class="text-[#1C2024] dark:text-white">{{ __('Dark Theme') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="setTheme('system')"
                        :class="theme === 'system' ? 'border-[#FFEF4D] bg-[#FFEF4D]/10' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]'"
                        class="flex flex-col items-center gap-2 p-3.5 rounded-[8px] border text-xs font-medium cursor-pointer transition shadow-none"
                    >
                        <div class="w-7 h-7 rounded-[6px] bg-slate-100 dark:bg-[#1E2433] text-[#60646C] dark:text-zinc-400 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-desktop"></i>
                        </div>
                        <span class="text-[#1C2024] dark:text-white">{{ __('System Sync') }}</span>
                    </button>
                </div>
            </div>

            <!-- 2. Password Update Card -->
            <div class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <div class="flex items-center gap-2.5">
                        <span class="p-1.5 rounded-[6px] bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 text-xs">
                            <i class="fa-solid fa-key"></i>
                        </span>
                        <div>
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-[#1C2024] dark:text-white">
                                {{ __('Update Password') }}
                            </h2>
                            <p class="text-[11px] text-[#60646C] dark:text-zinc-400">
                                {{ __('Ensure your administrative account uses a long, random password.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <form wire:submit="updatePassword" class="space-y-4">
                    <div>
                        <label for="current_password" class="block text-xs font-medium text-[#1C2024] dark:text-white mb-1.5">
                            {{ __('Current Password') }} <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="current_password"
                            type="password"
                            wire:model="current_password"
                            required
                            autocomplete="current-password"
                            class="w-full px-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
                        />
                        <x-input-error :messages="$errors->get('current_password')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="password" class="block text-xs font-medium text-[#1C2024] dark:text-white mb-1.5">
                                {{ __('New Password') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="password"
                                type="password"
                                wire:model="password"
                                required
                                autocomplete="new-password"
                                class="w-full px-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
                            />
                            <x-input-error :messages="$errors->get('password')" class="mt-1" />
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-xs font-medium text-[#1C2024] dark:text-white mb-1.5">
                                {{ __('Confirm New Password') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                wire:model="password_confirmation"
                                required
                                autocomplete="new-password"
                                class="w-full px-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
                            />
                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        @if (session('success_password'))
                            <div class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>{{ session('success_password') }}</span>
                            </div>
                        @else
                            <div></div>
                        @endif

                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-[6px] bg-[#12181E] hover:bg-[#1C2024] dark:bg-zinc-100 dark:hover:bg-white text-white dark:text-[#12181E] text-xs font-medium cursor-pointer transition shadow-none"
                        >
                            <i class="fa-solid fa-shield-check text-xs"></i>
                            <span>{{ __('Update Password') }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- 3. Two-Factor Authentication (2FA) Card -->
            @if ($canManageTwoFactor)
                <div class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                        <div class="flex items-center gap-2.5">
                            <span class="p-1.5 rounded-[6px] bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 text-xs">
                                <i class="fa-solid fa-shield-halved"></i>
                            </span>
                            <div>
                                <h2 class="text-xs font-semibold uppercase tracking-wider text-[#1C2024] dark:text-white">
                                    {{ __('Two-Factor Authentication (2FA)') }}
                                </h2>
                                <p class="text-[11px] text-[#60646C] dark:text-zinc-400">
                                    {{ __('Add extra security using TOTP authenticator apps (Google Authenticator, 1Password, etc).') }}
                                </p>
                            </div>
                        </div>

                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] text-[11px] font-medium {{ $twoFactorEnabled ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/40' : 'bg-slate-100 text-[#60646C] dark:bg-[#141821] dark:text-zinc-400' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $twoFactorEnabled ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            {{ $twoFactorEnabled ? __('Active') : __('Disabled') }}
                        </span>
                    </div>

                    @if (session('success_2fa'))
                        <div class="p-3 rounded-[6px] bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs font-medium text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>{{ session('success_2fa') }}</span>
                        </div>
                    @endif

                    <div class="space-y-4 text-xs" wire:cloak>
                        @if ($twoFactorEnabled)
                            <p class="text-[#60646C] dark:text-zinc-400 leading-relaxed">
                                {{ __('Two-factor authentication is active on your root account. You will be prompted for a 6-digit TOTP code during administrator logins.') }}
                            </p>

                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    wire:click="disableTwoFactor"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[6px] bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/40 text-rose-600 dark:text-rose-300 text-xs font-medium transition cursor-pointer border border-rose-200 dark:border-rose-800 shadow-none"
                                >
                                    <i class="fa-solid fa-lock-open text-xs"></i>
                                    <span>{{ __('Disable Two-Factor Authentication') }}</span>
                                </button>
                            </div>

                            <div class="pt-4 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                                <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                            </div>
                        @else
                            <p class="text-[#60646C] dark:text-zinc-400 leading-relaxed">
                                {{ __('When this is on, sign-in needs your password plus a code from your phone.') }}
                            </p>

                            <button
                                type="button"
                                x-data=""
                                x-on:click="$dispatch('open-modal', 'two-factor-setup-modal'); $wire.dispatch('start-two-factor-setup');"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-xs font-medium cursor-pointer transition shadow-none"
                            >
                                <i class="fa-solid fa-shield text-xs"></i>
                                <span>{{ __('Enable Two-Factor Authentication') }}</span>
                            </button>

                            <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                        @endif
                    </div>
                </div>
            @endif

            <!-- 4. Passkeys Card -->
            @if ($canManagePasskeys)
                <div class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                        <div class="flex items-center gap-2.5">
                            <span class="p-1.5 rounded-[6px] bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 text-xs">
                                <i class="fa-solid fa-fingerprint"></i>
                            </span>
                            <div>
                                <h2 class="text-xs font-semibold uppercase tracking-wider text-[#1C2024] dark:text-white">
                                    {{ __('Passkeys') }}
                                </h2>
                                <p class="text-[11px] text-[#60646C] dark:text-zinc-400">
                                    {{ __('Sign in securely with biometric Touch ID, Face ID, Windows Hello, or hardware security keys.') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    @if (session('success_passkey'))
                        <div class="p-3 rounded-[6px] bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs font-medium text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>{{ session('success_passkey') }}</span>
                        </div>
                    @endif

                    <div class="space-y-4 text-xs" wire:cloak>
                        <div class="border rounded-[8px] border-[#E4E5E9] dark:border-[#1E2433] overflow-hidden divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                            @forelse ($passkeys as $passkey)
                                <div class="flex items-center justify-between p-3.5 bg-white dark:bg-[#10141d] hover:bg-[#FAFAFB] dark:hover:bg-[#141821] transition">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-[6px] bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-900/60">
                                            <i class="fa-solid fa-fingerprint text-xs"></i>
                                        </div>
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-2">
                                                <p class="font-medium text-xs text-[#1C2024] dark:text-white">{{ $passkey['name'] }}</p>
                                                @if ($passkey['authenticator'])
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded-[4px] bg-[#EFEFF0] dark:bg-[#141821] font-medium text-[#60646C] dark:text-zinc-300">{{ $passkey['authenticator'] }}</span>
                                                @endif
                                            </div>
                                            <p class="text-[#60646C] dark:text-zinc-400 text-[11px]">
                                                {{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}
                                                @if ($passkey['last_used_at_diff'])
                                                    <span class="opacity-50 mx-1">&bull;</span>
                                                    {{ __('Last used :time', ['time' => $passkey['last_used_at_diff']]) }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="confirmDelete({{ $passkey['id'] }})"
                                        class="p-1.5 rounded-[6px] text-[#60646C] hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition cursor-pointer"
                                        title="{{ __('Remove Passkey') }}"
                                    >
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            @empty
                                <div class="p-8 text-center bg-white dark:bg-[#10141d] space-y-1">
                                    <i class="fa-solid fa-fingerprint text-xl text-[#60646C] dark:text-zinc-600 mb-1 block opacity-40"></i>
                                    <p class="font-medium text-xs text-[#1C2024] dark:text-white">{{ __('No passkeys registered yet') }}</p>
                                    <p class="text-[11px] text-[#60646C] dark:text-zinc-400">{{ __('Add a passkey to sign in with Face ID, Touch ID, or a security key.') }}</p>
                                </div>
                            @endforelse
                        </div>

                        <div class="pt-2">
                            <x-passkey-registration />
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Right: Role Overview & Security Info (1 Col) -->
        <div class="space-y-6">
            <!-- Admin Role Card -->
            <div class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-[8px] bg-[#FFEF4D] text-[#12181E] font-semibold text-sm flex items-center justify-center">
                        {{ auth()->user()?->initials() ?? 'AD' }}
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-medium text-sm text-[#1C2024] dark:text-white truncate">
                            {{ auth()->user()?->name ?? 'Platform Administrator' }}
                        </h3>
                        <p class="text-xs text-[#60646C] dark:text-zinc-400 font-mono truncate">
                            {{ auth()->user()?->email }}
                        </p>
                    </div>
                </div>

                <div class="p-3.5 rounded-[8px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[#60646C] dark:text-zinc-400 font-medium">{{ __('System Role') }}</span>
                        <span class="font-semibold text-[#856404] dark:text-[#FFEF4D]">{{ __('Platform Admin') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#60646C] dark:text-zinc-400 font-medium">{{ __('Access Level') }}</span>
                        <span class="font-semibold text-[#1C2024] dark:text-white">{{ __('Full access') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#60646C] dark:text-zinc-400 font-medium">{{ __('2FA Protection') }}</span>
                        <span class="font-semibold {{ $twoFactorEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-[#60646C] dark:text-zinc-400' }}">
                            {{ $twoFactorEnabled ? __('Active') : __('Disabled') }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#60646C] dark:text-zinc-400 font-medium">{{ __('Passkeys') }}</span>
                        <span class="font-semibold text-[#1C2024] dark:text-white">{{ count($passkeys) }} {{ __('Registered') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#60646C] dark:text-zinc-400 font-medium">{{ __('Account Created') }}</span>
                        <span class="font-mono text-[#1C2024] dark:text-zinc-300">{{ auth()->user()?->created_at?->format('d M Y') }}</span>
                    </div>
                </div>
            </div>

            <!-- Session & Impersonation Safety -->
            <div class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-3">
                <div class="flex items-center gap-2 text-xs font-semibold text-[#1C2024] dark:text-white">
                    <i class="fa-solid fa-user-lock text-[#856404] dark:text-[#FFEF4D]"></i>
                    <span>{{ __('This sign-in') }}</span>
                </div>
                <p class="text-xs text-[#60646C] dark:text-zinc-400 leading-relaxed">
                    {{ __('You are in admin. Open any operator from Operators without leaving this account.') }}
                </p>
                <div class="pt-2">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full h-9 rounded-[6px] bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-600 dark:text-rose-400 text-xs font-medium transition flex items-center justify-center gap-2 cursor-pointer border border-rose-200 dark:border-rose-900/60 shadow-none"
                        >
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span>{{ __('Log out') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Passkey Delete Confirmation Modal -->
    <div x-data="{ open: @entangle('showDeleteModal') }">
        <x-modal name="delete-passkey-modal" :show="$showDeleteModal" maxWidth="md">
            <div class="p-6 space-y-4">
                <div class="space-y-1">
                    <h3 class="text-sm font-semibold text-[#1C2024] dark:text-white">{{ __('Remove Passkey') }}</h3>
                    <p class="text-xs text-[#60646C] dark:text-zinc-400 leading-relaxed">
                        {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        wire:click="closeDeleteModal"
                        class="px-3 py-1.5 text-xs font-medium rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] transition cursor-pointer shadow-none"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="button"
                        wire:click="deletePasskey"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[6px] bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium cursor-pointer transition shadow-none"
                    >
                        {{ __('Remove Passkey') }}
                    </button>
                </div>
            </div>
        </x-modal>
    </div>
</div>
