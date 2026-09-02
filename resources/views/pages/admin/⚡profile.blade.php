<?php

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

new #[Title('Admin Profile & Security')] #[Layout('layouts.admin')] class extends Component {
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

        $user->fill($validated);
        $user->save();

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
        $this->twoFactorEnabled = false;
        session()->flash('success_2fa', __('Two-factor authentication has been disabled.'));
    }
}; ?>

<div class="space-y-6 w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="p-2.5 rounded-2xl bg-[#FFEF4D]/15 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30">
                <i class="fa-solid fa-user-shield text-lg"></i>
            </span>
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ __('Admin Profile & Security') }}
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Manage your Platform Master administrative credentials, 2FA authentication, and passkeys.') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-[#FFEF4D]/15 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30 shadow-2xs">
                <i class="fa-solid fa-crown text-[10px]"></i>
                <span>{{ __('Platform Master Root') }}</span>
            </span>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Account, Password, 2FA, Passkeys (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- 1. Administrative Identity Card -->
            <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-[#FFEF4D]/15 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30 text-xs">
                            <i class="fa-solid fa-id-card"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('Administrative Identity') }}
                            </h2>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ __('Your name and primary administrative email used for master notifications.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <form wire:submit="updateProfileInformation" class="space-y-4">
                    <div>
                        <x-label for="admin_name" :value="__('Full Name')" required />
                        <x-input
                            id="admin_name"
                            type="text"
                            wire:model="name"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="{{ __('Platform Administrator') }}"
                            :error="$errors->has('name')"
                        />
                        <x-input-error :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-label for="admin_email" :value="__('Admin Email Address')" required />
                        <x-input
                            id="admin_email"
                            type="email"
                            wire:model="email"
                            required
                            autocomplete="email"
                            placeholder="admin@emvi.dev"
                            :error="$errors->has('email')"
                        />
                        <x-input-error :messages="$errors->get('email')" />
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        @if (session('success_profile'))
                            <div class="flex items-center gap-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 animate-fade-in">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>{{ session('success_profile') }}</span>
                            </div>
                        @else
                            <div></div>
                        @endif

                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-xs shadow-xs transition flex items-center gap-2 cursor-pointer"
                        >
                            <i class="fa-solid fa-floppy-disk text-xs"></i>
                            <span>{{ __('Save Profile Details') }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Appearance & Theme Mode Card -->
            <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-[#FFEF4D]/15 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30 text-xs">
                            <i class="fa-solid fa-circle-half-stroke"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('Appearance & Theme Preference') }}
                            </h2>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ __('Choose your preferred color theme for Platform Master.') }}
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
                        :class="theme === 'light' ? 'ring-2 ring-[#FFEF4D] bg-[#FFEF4D]/10 dark:bg-[#FFEF4D]/15 border-[#FFEF4D]' : 'border-slate-200 dark:border-[#1e2433] bg-white dark:bg-[#0C0E13]'"
                        class="flex flex-col items-center gap-2.5 p-4 rounded-2xl border text-xs font-semibold cursor-pointer transition-all hover:border-[#FFEF4D]"
                    >
                        <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-500 flex items-center justify-center text-base">
                            <i class="fa-solid fa-sun"></i>
                        </div>
                        <span class="text-slate-900 dark:text-white">{{ __('Light Theme') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="setTheme('dark')"
                        :class="theme === 'dark' ? 'ring-2 ring-[#FFEF4D] bg-[#FFEF4D]/10 dark:bg-[#FFEF4D]/15 border-[#FFEF4D]' : 'border-slate-200 dark:border-[#1e2433] bg-white dark:bg-[#0C0E13]'"
                        class="flex flex-col items-center gap-2.5 p-4 rounded-2xl border text-xs font-semibold cursor-pointer transition-all hover:border-[#FFEF4D]"
                    >
                        <div class="w-8 h-8 rounded-xl bg-[#FFEF4D]/15 text-[#FFEF4D] border border-[#FFEF4D]/30 flex items-center justify-center text-base">
                            <i class="fa-solid fa-moon"></i>
                        </div>
                        <span class="text-slate-900 dark:text-white">{{ __('Dark Theme') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="setTheme('system')"
                        :class="theme === 'system' ? 'ring-2 ring-[#FFEF4D] bg-[#FFEF4D]/10 dark:bg-[#FFEF4D]/15 border-[#FFEF4D]' : 'border-slate-200 dark:border-[#1e2433] bg-white dark:bg-[#0C0E13]'"
                        class="flex flex-col items-center gap-2.5 p-4 rounded-2xl border text-xs font-semibold cursor-pointer transition-all hover:border-[#FFEF4D]"
                    >
                        <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-[#141821] text-slate-500 dark:text-slate-400 flex items-center justify-center text-base">
                            <i class="fa-solid fa-desktop"></i>
                        </div>
                        <span class="text-slate-900 dark:text-white">{{ __('System Sync') }}</span>
                    </button>
                </div>
            </div>

            <!-- 2. Password Update Card -->
            <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/70 text-rose-600 dark:text-rose-400 text-xs">
                            <i class="fa-solid fa-key"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('Update Password') }}
                            </h2>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ __('Ensure your administrative account uses a long, random password.') }}
                            </p>
                        </div>
                    </div>
                </div>

                <form wire:submit="updatePassword" class="space-y-4">
                    <div>
                        <x-label for="current_password" :value="__('Current Password')" required />
                        <x-input
                            id="current_password"
                            type="password"
                            wire:model="current_password"
                            required
                            autocomplete="current-password"
                            :error="$errors->has('current_password')"
                        />
                        <x-input-error :messages="$errors->get('current_password')" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-label for="password" :value="__('New Password')" required />
                            <x-input
                                id="password"
                                type="password"
                                wire:model="password"
                                required
                                autocomplete="new-password"
                                :error="$errors->has('password')"
                            />
                            <x-input-error :messages="$errors->get('password')" />
                        </div>

                        <div>
                            <x-label for="password_confirmation" :value="__('Confirm New Password')" required />
                            <x-input
                                id="password_confirmation"
                                type="password"
                                wire:model="password_confirmation"
                                required
                                autocomplete="new-password"
                                :error="$errors->has('password_confirmation')"
                            />
                            <x-input-error :messages="$errors->get('password_confirmation')" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        @if (session('success_password'))
                            <div class="flex items-center gap-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 animate-fade-in">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>{{ session('success_password') }}</span>
                            </div>
                        @else
                            <div></div>
                        @endif

                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-zinc-100 dark:hover:bg-white text-white dark:text-zinc-900 font-bold text-xs shadow-xs transition flex items-center gap-2 cursor-pointer"
                        >
                            <i class="fa-solid fa-shield-check text-xs"></i>
                            <span>{{ __('Update Password') }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- 3. Two-Factor Authentication (2FA) Card -->
            @if ($canManageTwoFactor)
                <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-[#1e2433]">
                        <div class="flex items-center gap-2.5">
                            <span class="p-2 rounded-xl bg-sky-50 dark:bg-sky-950/70 text-sky-600 dark:text-sky-400 text-xs">
                                <i class="fa-solid fa-shield-halved"></i>
                            </span>
                            <div>
                                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    {{ __('Two-Factor Authentication (2FA)') }}
                                </h2>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ __('Add extra security using TOTP authenticator apps (Google Authenticator, 1Password, etc).') }}
                                </p>
                            </div>
                        </div>

                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold {{ $twoFactorEnabled ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-[#141821] dark:text-slate-400' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $twoFactorEnabled ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            {{ $twoFactorEnabled ? __('Active') : __('Disabled') }}
                        </span>
                    </div>

                    @if (session('success_2fa'))
                        <div class="p-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-xs font-bold text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>{{ session('success_2fa') }}</span>
                        </div>
                    @endif

                    <div class="space-y-4 text-xs" wire:cloak>
                        @if ($twoFactorEnabled)
                            <p class="text-slate-600 dark:text-slate-400 leading-relaxed">
                                {{ __('Two-factor authentication is active on your root account. You will be prompted for a 6-digit TOTP code during administrator logins.') }}
                            </p>

                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    wire:click="disableTwoFactor"
                                    class="px-4 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/70 dark:hover:bg-rose-900/70 text-rose-600 dark:text-rose-300 text-xs font-bold transition flex items-center gap-2 cursor-pointer border border-rose-200 dark:border-rose-800"
                                >
                                    <i class="fa-solid fa-lock-open text-xs"></i>
                                    <span>{{ __('Disable Two-Factor Authentication') }}</span>
                                </button>
                            </div>

                            <div class="pt-4 border-t border-slate-100 dark:border-[#1e2433]">
                                <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                            </div>
                        @else
                            <p class="text-slate-500 dark:text-slate-400 leading-relaxed">
                                {{ __('When enabled, logging into Platform Master will require both your password and a temporary verification code from your authenticator app.') }}
                            </p>

                            <button
                                type="button"
                                x-data=""
                                x-on:click="$dispatch('open-modal', 'two-factor-setup-modal'); $wire.dispatch('start-two-factor-setup');"
                                class="px-4 py-2.5 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] text-xs font-black shadow-xs transition flex items-center gap-2 cursor-pointer"
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
                <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-[#1e2433]">
                        <div class="flex items-center gap-2.5">
                            <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 text-xs">
                                <i class="fa-solid fa-fingerprint"></i>
                            </span>
                            <div>
                                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                    {{ __('WebAuthn Passkeys') }}
                                </h2>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ __('Sign in securely with biometric Touch ID, Face ID, Windows Hello, or hardware security keys.') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    @if (session('success_passkey'))
                        <div class="p-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-xs font-bold text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>{{ session('success_passkey') }}</span>
                        </div>
                    @endif

                    <div class="space-y-4 text-xs" wire:cloak>
                        <div class="border rounded-2xl border-slate-200/80 dark:border-[#1e2433] overflow-hidden divide-y divide-slate-100 dark:divide-[#1e2433]">
                            @forelse ($passkeys as $passkey)
                                <div class="flex items-center justify-between p-4 bg-white dark:bg-[#0C0E13] hover:bg-slate-50/50 dark:hover:bg-[#141821]/30 transition">
                                    <div class="flex items-center gap-3.5">
                                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-900/60">
                                            <i class="fa-solid fa-fingerprint text-base"></i>
                                        </div>
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-2">
                                                <p class="font-bold text-slate-900 dark:text-white">{{ $passkey['name'] }}</p>
                                                @if ($passkey['authenticator'])
                                                    <span class="text-[10px] px-2 py-0.5 rounded-md bg-slate-100 dark:bg-[#141821] font-semibold text-slate-600 dark:text-slate-300">{{ $passkey['authenticator'] }}</span>
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

                                    <button
                                        type="button"
                                        wire:click="confirmDelete({{ $passkey['id'] }})"
                                        class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition cursor-pointer"
                                        title="{{ __('Remove Passkey') }}"
                                    >
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            @empty
                                <div class="p-8 text-center bg-white dark:bg-[#0C0E13] space-y-1">
                                    <i class="fa-solid fa-fingerprint text-2xl text-slate-300 dark:text-zinc-600 mb-1 block"></i>
                                    <p class="font-bold text-slate-700 dark:text-slate-300">{{ __('No passkeys registered yet') }}</p>
                                    <p class="text-xs text-slate-400">{{ __('Add a passkey to sign into Platform Master seamlessly without typing passwords.') }}</p>
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
            <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-[#FFEF4D] text-[#090d16] font-black text-base flex items-center justify-center shadow-xs">
                        {{ auth()->user()?->initials() ?? 'AD' }}
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-extrabold text-sm text-slate-900 dark:text-white truncate">
                            {{ auth()->user()?->name ?? 'Platform Administrator' }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 font-mono truncate">
                            {{ auth()->user()?->email }}
                        </p>
                    </div>
                </div>

                <div class="p-3.5 rounded-2xl bg-[#FFEF4D]/15 border border-[#FFEF4D]/30 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400 font-medium">{{ __('System Role') }}</span>
                        <span class="font-bold text-[#8a7808] dark:text-[#FFEF4D]">{{ __('Platform Admin') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400 font-medium">{{ __('Access Level') }}</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ __('Full Platform Root') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400 font-medium">{{ __('2FA Protection') }}</span>
                        <span class="font-bold {{ $twoFactorEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">
                            {{ $twoFactorEnabled ? __('Active') : __('Disabled') }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400 font-medium">{{ __('Passkeys') }}</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ count($passkeys) }} {{ __('Registered') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400 font-medium">{{ __('Account Created') }}</span>
                        <span class="font-mono text-slate-700 dark:text-slate-300">{{ auth()->user()?->created_at?->format('d M Y') }}</span>
                    </div>
                </div>
            </div>

            <!-- Session & Impersonation Safety -->
            <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-3">
                <div class="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300">
                    <i class="fa-solid fa-user-lock text-[#8a7808] dark:text-[#FFEF4D]"></i>
                    <span>{{ __('Administrative Session Guard') }}</span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    {{ __('Your session is protected by the dedicated Platform Master admin middleware. You can impersonate operator portals at any time from Operators Management without compromising root credentials.') }}
                </p>
                <div class="pt-2">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full h-10 rounded-xl bg-rose-50 dark:bg-rose-950/50 hover:bg-rose-100 dark:hover:bg-rose-900/50 text-rose-600 dark:text-rose-400 text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer border border-rose-200 dark:border-rose-900/60"
                        >
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span>{{ __('Log Out of Platform Master') }}</span>
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
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Remove Passkey') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
                    </p>
                </div>

                <div class="flex justify-end gap-2.5 pt-2">
                    <x-button variant="outline" size="sm" wire:click="closeDeleteModal">
                        {{ __('Cancel') }}
                    </x-button>
                    <x-button variant="danger" size="sm" wire:click="deletePasskey">
                        {{ __('Remove Passkey') }}
                    </x-button>
                </div>
            </div>
        </x-modal>
    </div>
</div>
