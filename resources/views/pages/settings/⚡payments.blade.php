<?php

use App\Models\Operator;
use App\Concerns\ResolvesCurrentOperator;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Payment Gateways')] class extends Component {
    use ResolvesCurrentOperator;
    // Bank Payout Settlement
    public string $bank_provider = 'BCA';
    public string $bank_account_name = '';
    public string $bank_account_number = '';
    public string $bank_account_ref = '';

    // Payment Processing Method: 'platform' (Built-in Doku Managed) vs 'custom' (BYO Merchant Account)
    public string $payment_mode = 'platform';

    // Custom Gateway Configuration
    public string $selected_gateway_provider = 'doku'; // doku (exclusive platform gateway)
    public string $gateway_environment = 'sandbox'; // sandbox, production
    public string $gateway_client_id = '';
    public string $gateway_shared_key = '';

    public bool $saved = false;


    /**
     * Mount the component.
     */
    public function mount(): void
    {
        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if ($operator) {
            $this->bank_provider = $operator->bank_provider ?? 'BCA';
            $this->bank_account_name = $operator->bank_account_name ?? '';
            $this->bank_account_number = $operator->bank_account_number ?? '';
            $this->bank_account_ref = $operator->bank_account_ref ?? '';

            $settings = $operator->settings ?? [];
            $gateway = $settings['payment_gateway'] ?? [];

            $useCustom = (bool) ($gateway['use_custom_credentials'] ?? false);
            $this->payment_mode = $useCustom ? 'custom' : 'platform';
            $this->selected_gateway_provider = 'doku';
            $this->gateway_environment = (string) ($gateway['environment'] ?? 'sandbox');
            $this->gateway_client_id = (string) ($gateway['client_id'] ?? '');
            $this->gateway_shared_key = (string) ($gateway['shared_key'] ?? '');
        }
    }

    /**
     * Update operator payment gateway and settlement details.
     */
    public function updatePaymentSettings(): void
    {
        $isCustom = $this->payment_mode === 'custom';

        if ($isCustom && ! $this->currentOperator?->hasFeature('byo_gateway')) {
            $this->addError('payment_mode', __('Connecting a custom payment gateway requires the Agency Ultimate subscription plan.'));
            return;
        }

        $validated = $this->validate([
            'bank_provider' => ['required', 'string', 'max:100'],
            'bank_account_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'payment_mode' => ['required', 'string', 'in:platform,custom'],
            'selected_gateway_provider' => [$isCustom ? 'required' : 'nullable', 'string', 'in:doku'],
            'gateway_environment' => [$isCustom ? 'required' : 'nullable', 'string', 'in:sandbox,production'],
            'gateway_client_id' => [$isCustom ? 'required' : 'nullable', 'string', 'max:255'],
            'gateway_shared_key' => [$isCustom ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if ($operator) {
            $settings = $operator->settings ?? [];
            $settings['payment_gateway'] = [
                'provider' => 'doku',
                'use_custom_credentials' => $isCustom,
                'environment' => $isCustom ? $validated['gateway_environment'] : 'production',
                'client_id' => $isCustom ? ($validated['gateway_client_id'] ?? null) : null,
                'shared_key' => $isCustom ? ($validated['gateway_shared_key'] ?? null) : null,
            ];

            $bankRef = ($validated['bank_provider'] && $validated['bank_account_number'])
                ? "{$validated['bank_provider']} - {$validated['bank_account_number']}" . ($validated['bank_account_name'] ? " ({$validated['bank_account_name']})" : '')
                : null;

            $operator->update([
                'bank_provider' => $validated['bank_provider'],
                'bank_account_name' => $validated['bank_account_name'],
                'bank_account_number' => $validated['bank_account_number'],
                'bank_account_ref' => $bankRef,
                'settings' => $settings,
            ]);
        }

        $this->saved = true;
        $this->dispatch('payments-updated');
    }
}; ?>

<div class="space-y-6 max-w-6xl mx-auto">
    <!-- Desktop Notice on Mobile -->
    <x-desktop-only-notice
        :title="__('Payment Gateway Setup Best Managed on Desktop')"
        :description="__('Configuring merchant credentials, webhook secrets, and bank payout details is best performed on desktop.')"
    />

    <div class="hidden lg:block space-y-6">
        <!-- Unified Settings Navigation -->
        <x-settings-nav />

    <!-- Standalone Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400">
                    <i class="fa-solid fa-credit-card text-lg"></i>
                </span>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __('Payment Gateways & Payouts') }}
                </h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Choose your payment processing method and set your bank account for automated direct payout settlements.') }}
            </p>
        </div>
    </div>

    <!-- Main Settings Form -->
    <form wire:submit="updatePaymentSettings" class="w-full space-y-6">
        <!-- Card 1: Bank Payout Settlement Account -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 text-xs">
                    <i class="fa-solid fa-building-columns"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Bank Payout Settlement Account') }}
                </h3>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('All customer payments processed via our built-in DOKU gateway are automatically disbursed into this designated Indonesian bank account.') }}
            </p>

            <!-- Bank Details Grid with Searchable Select -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- 1. Bank Provider -->
                <div>
                    <x-label for="bank_provider" :value="__('Bank Provider')" required />
                    <x-select
                        id="bank_provider"
                        wire:model="bank_provider"
                        :searchable="true"
                        :options="[
                            'BCA' => 'BCA (Bank Central Asia)',
                            'Mandiri' => 'Bank Mandiri',
                            'BRI' => 'BRI (Bank Rakyat Indonesia)',
                            'BNI' => 'BNI (Bank Negara Indonesia)',
                            'BSI' => 'BSI (Bank Syariah Indonesia)',
                            'CIMB Niaga' => 'CIMB Niaga',
                            'Permata' => 'Bank Permata',
                            'Danamon' => 'Bank Danamon',
                            'Bank Jago' => 'Bank Jago',
                            'SeaBank' => 'SeaBank',
                            'Other' => 'Other Bank',
                        ]"
                        :error="$errors->has('bank_provider')"
                    />
                    <x-input-error :messages="$errors->get('bank_provider')" />
                </div>

                <!-- 2. Account Name -->
                <div>
                    <x-label for="bank_account_name" :value="__('Beneficiary Account Name')" required />
                    <x-input
                        id="bank_account_name"
                        wire:model="bank_account_name"
                        type="text"
                        placeholder="e.g. PT Bali Adventures / John Doe"
                        :error="$errors->has('bank_account_name')"
                    />
                    <x-input-error :messages="$errors->get('bank_account_name')" />
                </div>

                <!-- 3. Account Number -->
                <div>
                    <x-label for="bank_account_number" :value="__('Account Number')" required />
                    <x-input
                        id="bank_account_number"
                        wire:model="bank_account_number"
                        type="text"
                        placeholder="e.g. 1234567890"
                        class="font-mono"
                        :error="$errors->has('bank_account_number')"
                    />
                    <x-input-error :messages="$errors->get('bank_account_number')" />
                </div>
            </div>
        </div>

        <!-- Card 2: Payment Processing Method -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-xs">
                    <i class="fa-solid fa-wallet"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Payment Processing Method') }}
                </h3>
            </div>

            <!-- Choice Grid: Built-in vs BYO Custom -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Option 1: Built-in Platform Managed DOKU Gateway -->
                <div
                    wire:click="$set('payment_mode', 'platform')"
                    class="relative p-5 rounded-2xl border-2 transition-all cursor-pointer select-none space-y-3 {{ $payment_mode === 'platform' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/40 dark:border-indigo-500 shadow-sm' : 'border-slate-200/80 dark:border-zinc-800 hover:border-slate-300 dark:hover:border-zinc-700 bg-white dark:bg-zinc-900' }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span class="p-2 rounded-xl bg-indigo-600 text-white shadow-xs">
                                <i class="fa-solid fa-building-circle-check text-sm"></i>
                            </span>
                            <div>
                                <h4 class="font-extrabold text-sm text-slate-900 dark:text-white">
                                    {{ __('Built-in Platform Payment') }}
                                </h4>
                                <span class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400">
                                    {{ __('Managed via DOKU Payment Gateway') }}
                                </span>
                            </div>
                        </div>

                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                            {{ __('Recommended') }}
                        </span>
                    </div>

                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        {{ __('We take care of all payment gateway contracts, technical compliance, and transaction routing for you. Zero API setup required.') }}
                    </p>

                    <!-- Supported Channels Badges -->
                    <div class="pt-2 border-t border-slate-200/60 dark:border-zinc-700/60 space-y-1.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">{{ __('Channels Included') }}</span>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-slate-300">QRIS (All Banks & E-Wallets)</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-slate-300">BCA, Mandiri, BRI, BNI VA</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-slate-300">Visa / Mastercard / JCB</span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-slate-300">OVO, DANA, ShopeePay</span>
                        </div>
                    </div>
                </div>

                <!-- Option 2: BYO Custom Merchant Account -->
                <div
                    wire:click="$set('payment_mode', 'custom')"
                    class="relative p-5 rounded-2xl border-2 transition-all cursor-pointer select-none space-y-3 {{ $payment_mode === 'custom' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/40 dark:border-indigo-500 shadow-sm' : 'border-slate-200/80 dark:border-zinc-800 hover:border-slate-300 dark:hover:border-zinc-700 bg-white dark:bg-zinc-900' }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span class="p-2 rounded-xl bg-slate-200 dark:bg-zinc-800 text-slate-700 dark:text-slate-300">
                                <i class="fa-solid fa-sliders text-sm"></i>
                            </span>
                            <div>
                                <h4 class="font-extrabold text-sm text-slate-900 dark:text-white">
                                    {{ __('Custom Gateway (BYO Account)') }}
                                </h4>
                                <span class="text-[11px] text-slate-500">
                                    {{ __('Bring Your Own Credentials') }}
                                </span>
                            </div>
                        </div>

                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-slate-400">
                            {{ __('Agency Ultimate') }}
                        </span>
                    </div>

                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        {{ __('Connect your own direct merchant credentials if your business has an existing merchant account with DOKU.') }}
                    </p>

                    <div class="pt-2 border-t border-slate-200/60 dark:border-zinc-700/60 flex items-center gap-2 text-xs text-slate-500">
                        <i class="fa-solid fa-code text-[11px]"></i>
                        <span>{{ __('Requires DOKU Client ID (MALL ID) & Secret / Shared Key') }}</span>
                    </div>
                </div>
            </div>

            <!-- Built-in Platform Mode Information Banner -->
            @if ($payment_mode === 'platform')
                <div class="p-4 rounded-2xl bg-emerald-50/80 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/60 flex items-start gap-3.5 animate-fade-in">
                    <span class="p-1.5 rounded-xl bg-emerald-600 text-white shrink-0 mt-0.5">
                        <i class="fa-solid fa-circle-check text-xs"></i>
                    </span>
                    <div class="space-y-1">
                        <h5 class="text-xs sm:text-sm font-bold text-emerald-900 dark:text-emerald-200">
                            {{ __('Platform Managed DOKU Payment is Active') }}
                        </h5>
                        <p class="text-[11px] sm:text-xs text-emerald-800/90 dark:text-emerald-300/90 leading-relaxed">
                            {{ __('Your storefront checkout will automatically route guest reservations through our verified DOKU payment engine. When a guest pays, transaction proceeds are deposited into your designated bank account above.') }}
                        </p>
                    </div>
                </div>
            @endif

            <!-- Custom Gateway BYO Config Fields -->
            @if ($payment_mode === 'custom')
                @if (! $this->currentOperator?->hasFeature('byo_gateway'))
                    <div class="p-5 rounded-2xl bg-gradient-to-r from-purple-500/10 via-indigo-500/10 to-transparent border border-purple-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3 animate-fade-in">
                        <div class="flex items-center gap-2.5">
                            <span class="p-2.5 rounded-xl bg-purple-100 dark:bg-purple-950/80 text-purple-600 dark:text-purple-400 text-sm">
                                <i class="fa-solid fa-crown"></i>
                            </span>
                            <div>
                                <p class="text-xs font-bold text-slate-900 dark:text-white">{{ __('Custom Gateway Requires Agency Ultimate Tier') }}</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Upgrade to Agency Ultimate to connect your own direct DOKU merchant credentials.') }}</p>
                            </div>
                        </div>
                        <a href="{{ route('settings.plan') }}" class="px-3.5 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-xs transition inline-flex items-center gap-1.5 shrink-0 self-start sm:self-auto shadow-xs" wire:navigate>
                            <i class="fa-solid fa-crown text-[10px] text-amber-300"></i>
                            <span>{{ __('Upgrade to Agency Ultimate') }}</span>
                        </a>
                    </div>
                @endif

                <div class="p-5 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200 dark:border-zinc-800 space-y-5 animate-fade-in {{ ! $this->currentOperator?->hasFeature('byo_gateway') ? 'opacity-50 pointer-events-none' : '' }}">
                    <div>
                        <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">
                            {{ __('Direct DOKU Merchant Credentials') }}
                        </h4>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            {{ __('Specify your direct DOKU Checkout & SNAP API merchant keys.') }}
                        </p>
                    </div>

                    <!-- Single Provider Active Badge -->
                    <div class="p-4 rounded-xl border-2 border-indigo-600 bg-white dark:bg-zinc-900 shadow-xs flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="p-2 rounded-lg bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400">
                                <i class="fa-solid fa-credit-card text-sm"></i>
                            </span>
                            <div>
                                <span class="font-bold text-xs text-slate-900 dark:text-white block">{{ __('DOKU Checkout & SNAP API') }}</span>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Exclusive platform payment engine') }}</span>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                            {{ __('Supported Gateway') }}
                        </span>
                    </div>

                    <!-- Custom API Credential Inputs -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                        <div>
                            <x-label for="gateway_environment" :value="__('Gateway Environment')" required />
                            <x-select
                                id="gateway_environment"
                                wire:model="gateway_environment"
                                :options="[
                                    'sandbox' => 'Sandbox (Testing & Demo)',
                                    'production' => 'Production (Live Payments)',
                                ]"
                            />
                            <x-input-error :messages="$errors->get('gateway_environment')" />
                        </div>

                        <div>
                            <x-label for="gateway_client_id" :value="__('DOKU Client ID / MALL ID')" required />
                            <x-input id="gateway_client_id" wire:model="gateway_client_id" type="text" placeholder="e.g. MALLID_12345" class="font-mono text-xs" :error="$errors->has('gateway_client_id')" />
                            <x-input-error :messages="$errors->get('gateway_client_id')" />
                        </div>

                        <div>
                            <x-label for="gateway_shared_key" :value="__('DOKU Secret / Shared Key')" required />
                            <x-input id="gateway_shared_key" wire:model="gateway_shared_key" type="password" placeholder="••••••••••••••••" class="font-mono text-xs" :error="$errors->has('gateway_shared_key')" />
                            <x-input-error :messages="$errors->get('gateway_shared_key')" />
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Submit Button & Success Toast -->
        <div class="flex items-center gap-4 pt-2">
            <x-button variant="primary" type="submit" data-test="update-payments-button" class="shadow-sm">
                <i class="fa-solid fa-floppy-disk mr-1 text-xs"></i>
                {{ __('Save Payment Settings') }}
            </x-button>

            <div x-data="{ shown: false, timeout: null }"
                 x-init="@this.on('payments-updated', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2500); })"
                 x-show.transition.out.opacity.duration.1500ms="shown"
                 x-transition:leave.opacity.duration.1500ms
                 style="display: none;"
                 class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                <i class="fa-solid fa-circle-check"></i>
                {{ __('Payment & settlement settings saved successfully.') }}
            </div>
        </div>
    </form>
    </div>
</div>
