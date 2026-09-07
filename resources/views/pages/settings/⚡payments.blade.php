<?php

use App\Models\Operator;
use App\Concerns\ResolvesCurrentOperator;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Payout bank account')] class extends Component {
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

            $this->payment_mode = 'platform';
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
        $this->authorizeAbility('manageBilling');

        $isCustom = $this->payment_mode === 'custom';

        if ($isCustom) {
            $this->addError('payment_mode', __('Guests always pay through EMVI. The Agency plan adds extra tools, not your own payment account.'));

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

<div class="space-y-6 w-full">
    <!-- Desktop Notice on Mobile -->
    <x-desktop-only-notice
        :title="__('Bank details are easier on a computer')"
        :description="__('Type your payout bank account on a larger screen so the numbers are easy to check.')"
    />

    <div class="hidden lg:block space-y-6">
        <!-- Unified Billing Navigation -->
        <x-billing-nav />

    <!-- Standalone Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-300">
                    <i class="fa-solid fa-credit-card text-lg"></i>
                </span>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __('Payout bank account') }}
                </h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Tell us where to send your money after a trip. Guests always pay through EMVI — you keep the listed price.') }}
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
                    {{ __('Where we send your money') }}
                </h3>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('After the trip, we send the listed price to this Indonesian bank account. Money is held until then.') }}
            </p>

            <!-- Bank Details Grid with Searchable Select -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- 1. Bank Provider -->
                <div>
                    <x-label for="bank_provider" :value="__('Bank')" required />
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
                    <x-label for="bank_account_name" :value="__('Name on the account')" required />
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

        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-3">
            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-xs">
                    <i class="fa-solid fa-wallet"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('How guests pay') }}
                </h3>
            </div>

            <p class="text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                {{ __('Guests pay on your storefront with QRIS, bank transfer, card, or e-wallet. We hold the money until the trip, then send the listed price to the bank account above. Every plan works this way — including Agency.') }}
            </p>

            <div class="flex flex-wrap gap-1.5">
                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-slate-300">QRIS</span>
                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-slate-300">Bank transfer</span>
                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-slate-300">Visa / Mastercard</span>
                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-slate-300">OVO, DANA, ShopeePay</span>
            </div>
        </div>

        <!-- Submit Button & Success Toast -->
        <div class="flex items-center gap-4 pt-2">
            <x-button variant="primary" type="submit" data-test="update-payments-button" class="shadow-sm">
                <i class="fa-solid fa-floppy-disk mr-1 text-xs"></i>
                {{ __('Save bank account') }}
            </x-button>

            <div x-data="{ shown: false, timeout: null }"
                 x-init="@this.on('payments-updated', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2500); })"
                 x-show.transition.out.opacity.duration.1500ms="shown"
                 x-transition:leave.opacity.duration.1500ms
                 style="display: none;"
                 class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                <i class="fa-solid fa-circle-check"></i>
                {{ __('Bank account saved.') }}
            </div>
        </div>
    </form>
    </div>
</div>
