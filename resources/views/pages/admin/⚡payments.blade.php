<?php

use App\Models\PlatformSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('DOKU Payment Gateway')] #[Layout('layouts.admin')] class extends Component {
    // Gateway Provider
    public string $selected_provider = 'doku';

    // DOKU Gateway Environment & Keys
    public string $doku_mode = 'sandbox'; // sandbox, live

    // Sandbox Credentials
    public string $sandbox_client_id = '';
    public string $sandbox_secret_key = '';
    public string $sandbox_doku_public_key = '';
    public string $sandbox_merchant_public_key = '';
    public string $sandbox_merchant_private_key = '';
    public string $sandbox_snap_token_url = 'https://api-sandbox.doku.com/authorization/v1/access-token/b2b';
    public string $sandbox_base_url = 'https://api-sandbox.doku.com';
    public string $sandbox_checkout_url = 'https://jokul-sandbox.doku.com/checkout';

    // Live Credentials
    public string $live_client_id = '';
    public string $live_secret_key = '';
    public string $live_doku_public_key = '';
    public string $live_merchant_public_key = '';
    public string $live_merchant_private_key = '';
    public string $live_snap_token_url = 'https://api.doku.com/authorization/v1/access-token/b2b';
    public string $live_base_url = 'https://api.doku.com';
    public string $live_checkout_url = 'https://jokul.doku.com/checkout';

    public bool $saved = false;

    /**
     * Mount the payment settings component.
     */
    public function mount(): void
    {
        $platform = PlatformSetting::current();
        $settings = $platform->settings ?? [];

        $doku = $settings['doku'] ?? [];
        $this->doku_mode = (string) ($doku['mode'] ?? ($settings['doku_mode'] ?? 'sandbox'));

        $sandbox = $doku['sandbox'] ?? [];
        $this->sandbox_client_id = (string) ($sandbox['client_id'] ?? config('doku.sandbox.client_id', ''));
        $this->sandbox_secret_key = (string) ($sandbox['secret_key'] ?? ($sandbox['shared_key'] ?? config('doku.sandbox.secret_key', '')));
        $this->sandbox_doku_public_key = (string) ($sandbox['doku_public_key'] ?? config('doku.sandbox.doku_public_key', ''));
        $this->sandbox_merchant_public_key = (string) ($sandbox['merchant_public_key'] ?? config('doku.sandbox.merchant_public_key', ''));
        $this->sandbox_merchant_private_key = (string) ($sandbox['merchant_private_key'] ?? config('doku.sandbox.merchant_private_key', ''));
        $this->sandbox_snap_token_url = (string) ($sandbox['snap_token_url'] ?? config('doku.sandbox.snap_token_url', 'https://api-sandbox.doku.com/authorization/v1/access-token/b2b'));
        $this->sandbox_base_url = (string) ($sandbox['base_url'] ?? config('doku.sandbox.base_url', 'https://api-sandbox.doku.com'));
        $this->sandbox_checkout_url = (string) ($sandbox['checkout_url'] ?? config('doku.sandbox.checkout_url', 'https://jokul-sandbox.doku.com/checkout'));

        $live = $doku['live'] ?? [];
        $this->live_client_id = (string) ($live['client_id'] ?? config('doku.live.client_id', ''));
        $this->live_secret_key = (string) ($live['secret_key'] ?? ($live['shared_key'] ?? config('doku.live.secret_key', '')));
        $this->live_doku_public_key = (string) ($live['doku_public_key'] ?? config('doku.live.doku_public_key', ''));
        $this->live_merchant_public_key = (string) ($live['merchant_public_key'] ?? config('doku.live.merchant_public_key', ''));
        $this->live_merchant_private_key = (string) ($live['merchant_private_key'] ?? config('doku.live.merchant_private_key', ''));
        $this->live_snap_token_url = (string) ($live['snap_token_url'] ?? config('doku.live.snap_token_url', 'https://api.doku.com/authorization/v1/access-token/b2b'));
        $this->live_base_url = (string) ($live['base_url'] ?? config('doku.live.base_url', 'https://api.doku.com'));
        $this->live_checkout_url = (string) ($live['checkout_url'] ?? config('doku.live.checkout_url', 'https://jokul.doku.com/checkout'));
    }

    /**
     * Save platform payment gateway settings.
     */
    public function updatePaymentSettings(): void
    {
        $validated = $this->validate([
            'doku_mode' => ['required', 'string', 'in:sandbox,live'],
            'sandbox_client_id' => ['nullable', 'string', 'max:255'],
            'sandbox_secret_key' => ['nullable', 'string', 'max:255'],
            'sandbox_doku_public_key' => ['nullable', 'string'],
            'sandbox_merchant_public_key' => ['nullable', 'string'],
            'sandbox_merchant_private_key' => ['nullable', 'string'],
            'sandbox_snap_token_url' => ['nullable', 'url', 'max:255'],
            'sandbox_base_url' => ['required', 'url', 'max:255'],
            'sandbox_checkout_url' => ['required', 'url', 'max:255'],

            'live_client_id' => ['nullable', 'string', 'max:255'],
            'live_secret_key' => ['nullable', 'string', 'max:255'],
            'live_doku_public_key' => ['nullable', 'string'],
            'live_merchant_public_key' => ['nullable', 'string'],
            'live_merchant_private_key' => ['nullable', 'string'],
            'live_snap_token_url' => ['nullable', 'url', 'max:255'],
            'live_base_url' => ['required', 'url', 'max:255'],
            'live_checkout_url' => ['required', 'url', 'max:255'],
        ]);

        $platform = PlatformSetting::current();
        $settings = $platform->settings ?? [];

        $settings['doku_mode'] = $validated['doku_mode'];
        $settings['doku'] = [
            'mode' => $validated['doku_mode'],
            'sandbox' => [
                'client_id' => $validated['sandbox_client_id'] ?? '',
                'secret_key' => $validated['sandbox_secret_key'] ?? '',
                'shared_key' => $validated['sandbox_secret_key'] ?? '',
                'doku_public_key' => $validated['sandbox_doku_public_key'] ?? '',
                'merchant_public_key' => $validated['sandbox_merchant_public_key'] ?? '',
                'merchant_private_key' => $validated['sandbox_merchant_private_key'] ?? '',
                'snap_token_url' => $validated['sandbox_snap_token_url'] ?? '',
                'base_url' => $validated['sandbox_base_url'],
                'checkout_url' => $validated['sandbox_checkout_url'],
            ],
            'live' => [
                'client_id' => $validated['live_client_id'] ?? '',
                'secret_key' => $validated['live_secret_key'] ?? '',
                'shared_key' => $validated['live_secret_key'] ?? '',
                'doku_public_key' => $validated['live_doku_public_key'] ?? '',
                'merchant_public_key' => $validated['live_merchant_public_key'] ?? '',
                'merchant_private_key' => $validated['live_merchant_private_key'] ?? '',
                'snap_token_url' => $validated['live_snap_token_url'] ?? '',
                'base_url' => $validated['live_base_url'],
                'checkout_url' => $validated['live_checkout_url'],
            ],
        ];

        $platform->update(['settings' => $settings]);

        $this->saved = true;
        $this->dispatch('payment-settings-updated');
    }
}; ?>

<div class="space-y-6 w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30">
                    <i class="fa-solid fa-credit-card text-lg"></i>
                </span>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('DOKU Payment Gateway') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Configure central platform DOKU Checkout & SNAP Open API credentials, Sandbox keys, Live production settings, and RSA certificates.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold {{ $doku_mode === 'live' ? 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/60' : 'bg-amber-950/60 text-amber-400 border border-amber-800/60' }}">
                <span class="w-2 h-2 rounded-full {{ $doku_mode === 'live' ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400' }}"></span>
                {{ $doku_mode === 'live' ? __('Live Production Active') : __('Sandbox Testing Active') }}
            </span>
        </div>
    </div>

    <!-- Active Gateway Engine Notice Banner -->
    <div class="p-4 rounded-3xl border border-slate-200/80 dark:border-[#1e2433] bg-white dark:bg-[#0C0E13] flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xs">
        <div class="flex items-start sm:items-center gap-3">
            <span class="p-2.5 rounded-2xl bg-[#FFEF4D] text-[#090d16] shrink-0 shadow-xs">
                <i class="fa-solid fa-building-columns text-sm"></i>
            </span>
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">{{ __('DOKU Hosted Checkout & SNAP Open API') }}</h3>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-950/60 text-emerald-400 border border-emerald-800/60">
                        {{ __('Exclusive Gateway') }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Processes QRIS, Virtual Accounts (BCA, Mandiri, BRI, BNI), Credit Cards, and automated operator bank split settlements.') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Main Payment Settings Form -->
    <form wire:submit="updatePaymentSettings" class="w-full space-y-6">
        <!-- Section: Central DOKU Payment Gateway -->
        <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                <div class="flex items-center gap-2.5">
                    <span class="p-1.5 rounded-lg bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30 text-xs">
                        <i class="fa-solid fa-building-circle-check"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Central DOKU Payment Credentials') }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('All agent checkouts without custom credentials will route through this central gateway account.') }}
                        </p>
                    </div>
                </div>

                <!-- Environment Switcher -->
                <div class="inline-flex rounded-xl bg-slate-100 dark:bg-[#141821] p-1 shrink-0 border border-slate-200 dark:border-[#1e2433]">
                    <button
                        type="button"
                        wire:click="$set('doku_mode', 'sandbox')"
                        class="px-3.5 py-1.5 text-xs font-black rounded-lg transition cursor-pointer {{ $doku_mode === 'sandbox' ? 'bg-[#FFEF4D] text-[#090d16] shadow-xs' : 'text-slate-600 dark:text-slate-400' }}"
                    >
                        <i class="fa-solid fa-flask mr-1 text-[10px]"></i>
                        {{ __('Sandbox (Testing)') }}
                    </button>
                    <button
                        type="button"
                        wire:click="$set('doku_mode', 'live')"
                        class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition cursor-pointer {{ $doku_mode === 'live' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400' }}"
                    >
                        <i class="fa-solid fa-bolt mr-1 text-[10px]"></i>
                        {{ __('Live (Production)') }}
                    </button>
                </div>
            </div>

            <!-- Credentials Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Sandbox Credentials Card -->
                <div class="p-5 rounded-2xl border {{ $doku_mode === 'sandbox' ? 'border-amber-400/60 dark:border-amber-600/60 bg-amber-50/20 dark:bg-amber-950/10' : 'border-slate-200/80 dark:border-[#1e2433] bg-slate-50/40 dark:bg-[#141821]/40' }} space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-flask text-amber-500 text-sm"></i>
                            <h4 class="font-bold text-xs uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                {{ __('Sandbox Environment Credentials') }}
                            </h4>
                        </div>
                        @if ($doku_mode === 'sandbox')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-amber-200 text-amber-900 dark:bg-amber-950/80 dark:text-amber-300 border border-amber-800/50">{{ __('Active') }}</span>
                        @endif
                    </div>

                    <!-- Core Keys -->
                    <div class="space-y-3">
                        <div>
                            <x-label for="sandbox_client_id" :value="__('Client ID / API Key')" />
                            <x-input id="sandbox_client_id" wire:model="sandbox_client_id" type="text" placeholder="e.g. MALLID_SANDBOX_12345" class="font-mono text-xs" :error="$errors->has('sandbox_client_id')" />
                            <x-input-error :messages="$errors->get('sandbox_client_id')" />
                        </div>

                        <div>
                            <x-label for="sandbox_secret_key" :value="__('Secret Key / Shared Key')" />
                            <x-input id="sandbox_secret_key" wire:model="sandbox_secret_key" type="password" placeholder="••••••••••••••••" class="font-mono text-xs" :error="$errors->has('sandbox_secret_key')" />
                            <x-input-error :messages="$errors->get('sandbox_secret_key')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                            <div>
                                <x-label for="sandbox_base_url" :value="__('API Base URL')" />
                                <x-input id="sandbox_base_url" wire:model="sandbox_base_url" type="url" class="font-mono text-xs" :error="$errors->has('sandbox_base_url')" />
                                <x-input-error :messages="$errors->get('sandbox_base_url')" />
                            </div>
                            <div>
                                <x-label for="sandbox_checkout_url" :value="__('Checkout URL')" />
                                <x-input id="sandbox_checkout_url" wire:model="sandbox_checkout_url" type="url" class="font-mono text-xs" :error="$errors->has('sandbox_checkout_url')" />
                                <x-input-error :messages="$errors->get('sandbox_checkout_url')" />
                            </div>
                        </div>
                    </div>

                    <!-- SNAP Open API & Keypair (Collapsible / Advanced) -->
                    <div x-data="{ openSnap: false }" class="pt-3 border-t border-slate-200 dark:border-[#1e2433] space-y-3">
                        <button type="button" @click="openSnap = !openSnap" class="w-full flex items-center justify-between text-xs font-bold text-[#8a7808] dark:text-[#FFEF4D] hover:underline cursor-pointer">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid fa-key text-[10px]"></i>
                                {{ __('SNAP Open API & Keypair Settings (Optional)') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200" :class="openSnap ? 'rotate-180' : ''"></i>
                        </button>

                        <div x-show="openSnap" x-cloak class="space-y-3 pt-2">
                            <div>
                                <x-label for="sandbox_snap_token_url" :value="__('SNAP Token URL')" />
                                <x-input id="sandbox_snap_token_url" wire:model="sandbox_snap_token_url" type="url" placeholder="https://api-sandbox.doku.com/authorization/v1/access-token/b2b" class="font-mono text-xs" :error="$errors->has('sandbox_snap_token_url')" />
                                <x-input-error :messages="$errors->get('sandbox_snap_token_url')" />
                            </div>

                            <div>
                                <x-label for="sandbox_doku_public_key" :value="__('DOKU Public Key')" />
                                <x-textarea id="sandbox_doku_public_key" wire:model="sandbox_doku_public_key" rows="2" placeholder="-----BEGIN PUBLIC KEY-----..." class="font-mono text-xs" />
                            </div>

                            <div>
                                <x-label for="sandbox_merchant_public_key" :value="__('Merchant Public Key')" />
                                <x-textarea id="sandbox_merchant_public_key" wire:model="sandbox_merchant_public_key" rows="2" placeholder="-----BEGIN PUBLIC KEY-----..." class="font-mono text-xs" />
                            </div>

                            <div>
                                <x-label for="sandbox_merchant_private_key" :value="__('Merchant Private Key')" />
                                <x-textarea id="sandbox_merchant_private_key" wire:model="sandbox_merchant_private_key" rows="2" placeholder="-----BEGIN RSA PRIVATE KEY-----..." class="font-mono text-xs" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live / Production Credentials Card -->
                <div class="p-5 rounded-2xl border {{ $doku_mode === 'live' ? 'border-emerald-500/60 dark:border-emerald-600/60 bg-emerald-50/20 dark:bg-emerald-950/10' : 'border-slate-200/80 dark:border-[#1e2433] bg-slate-50/40 dark:bg-[#141821]/40' }} space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-bolt text-emerald-500 text-sm"></i>
                            <h4 class="font-bold text-xs uppercase tracking-wider text-slate-800 dark:text-slate-200">
                                {{ __('Live Production Credentials') }}
                            </h4>
                        </div>
                        @if ($doku_mode === 'live')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-200 text-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-800/50">{{ __('Active') }}</span>
                        @endif
                    </div>

                    <!-- Core Keys -->
                    <div class="space-y-3">
                        <div>
                            <x-label for="live_client_id" :value="__('Client ID / API Key')" />
                            <x-input id="live_client_id" wire:model="live_client_id" type="text" placeholder="e.g. MALLID_LIVE_98765" class="font-mono text-xs" :error="$errors->has('live_client_id')" />
                            <x-input-error :messages="$errors->get('live_client_id')" />
                        </div>

                        <div>
                            <x-label for="live_secret_key" :value="__('Secret Key / Shared Key')" />
                            <x-input id="live_secret_key" wire:model="live_secret_key" type="password" placeholder="••••••••••••••••" class="font-mono text-xs" :error="$errors->has('live_secret_key')" />
                            <x-input-error :messages="$errors->get('live_secret_key')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                            <div>
                                <x-label for="live_base_url" :value="__('API Base URL')" />
                                <x-input id="live_base_url" wire:model="live_base_url" type="url" class="font-mono text-xs" :error="$errors->has('live_base_url')" />
                                <x-input-error :messages="$errors->get('live_base_url')" />
                            </div>
                            <div>
                                <x-label for="live_checkout_url" :value="__('Checkout URL')" />
                                <x-input id="live_checkout_url" wire:model="live_checkout_url" type="url" class="font-mono text-xs" :error="$errors->has('live_checkout_url')" />
                                <x-input-error :messages="$errors->get('live_checkout_url')" />
                            </div>
                        </div>
                    </div>

                    <!-- SNAP Open API & Keypair (Collapsible / Advanced) -->
                    <div x-data="{ openSnapLive: false }" class="pt-3 border-t border-slate-200 dark:border-[#1e2433] space-y-3">
                        <button type="button" @click="openSnapLive = !openSnapLive" class="w-full flex items-center justify-between text-xs font-bold text-[#8a7808] dark:text-[#FFEF4D] hover:underline cursor-pointer">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid fa-key text-[10px]"></i>
                                {{ __('SNAP Open API & Keypair Settings (Optional)') }}
                            </span>
                            <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200" :class="openSnapLive ? 'rotate-180' : ''"></i>
                        </button>

                        <div x-show="openSnapLive" x-cloak class="space-y-3 pt-2">
                            <div>
                                <x-label for="live_snap_token_url" :value="__('SNAP Token URL')" />
                                <x-input id="live_snap_token_url" wire:model="live_snap_token_url" type="url" placeholder="https://api.doku.com/authorization/v1/access-token/b2b" class="font-mono text-xs" :error="$errors->has('live_snap_token_url')" />
                                <x-input-error :messages="$errors->get('live_snap_token_url')" />
                            </div>

                            <div>
                                <x-label for="live_doku_public_key" :value="__('DOKU Public Key')" />
                                <x-textarea id="live_doku_public_key" wire:model="live_doku_public_key" rows="2" placeholder="-----BEGIN PUBLIC KEY-----..." class="font-mono text-xs" />
                            </div>

                            <div>
                                <x-label for="live_merchant_public_key" :value="__('Merchant Public Key')" />
                                <x-textarea id="live_merchant_public_key" wire:model="live_merchant_public_key" rows="2" placeholder="-----BEGIN PUBLIC KEY-----..." class="font-mono text-xs" />
                            </div>

                            <div>
                                <x-label for="live_merchant_private_key" :value="__('Merchant Private Key')" />
                                <x-textarea id="live_merchant_private_key" wire:model="live_merchant_private_key" rows="2" placeholder="-----BEGIN RSA PRIVATE KEY-----..." class="font-mono text-xs" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webhook Notification Endpoint Guidance -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-[#141821]/50 border border-slate-200 dark:border-[#1e2433] space-y-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                    <i class="fa-solid fa-webhook text-[#8a7808] dark:text-[#FFEF4D]"></i>
                    {{ __('DOKU Webhook Notification URL (Register in DOKU Merchant Dashboard)') }}
                </span>
                <div class="flex items-center gap-3">
                    <code class="flex-1 px-3 py-2 rounded-xl bg-white dark:bg-[#0C0E13] border border-slate-200 dark:border-[#1e2433] font-mono text-xs text-[#8a7808] dark:text-[#FFEF4D] select-all">
                        {{ url('/api/v1/payments/doku/notify') }}
                    </code>
                </div>
            </div>
        </div>

        <!-- Submit Button & Success Toast -->
        <div class="flex items-center gap-4 pt-2">
            <button type="submit" data-test="save-payment-settings-button" class="h-10 px-5 rounded-2xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-xs inline-flex items-center gap-2 shadow-xs transition cursor-pointer">
                <i class="fa-solid fa-floppy-disk text-xs"></i>
                {{ __('Save Gateway Settings') }}
            </button>

            <div x-data="{ shown: false, timeout: null }"
                 x-init="@this.on('payment-settings-updated', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2500); })"
                 x-show.transition.out.opacity.duration.1500ms="shown"
                 x-transition:leave.opacity.duration.1500ms
                 style="display: none;"
                 class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                <i class="fa-solid fa-circle-check"></i>
                {{ __('Gateway credentials and environment saved successfully.') }}
            </div>
        </div>
    </form>
</div>
