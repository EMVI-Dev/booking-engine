<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\PlatformSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Platform Settings')] #[Layout('layouts.admin')] class extends Component {
    // Global Platform Parameters
    public string $platform_name = 'TravelEngine';
    public string $support_email = 'admin@emvi.dev';
    public float $commission_percentage = 0.0; // 0% operator commission
    public float $guest_service_fee_percentage = 5.0; // 5% guest service fee
    public int $booking_hold_minutes = 30;
    public string $currency_code = 'IDR';
    public string $currency_symbol = 'Rp';

    public int $approved_operators = 0;
    public int $total_operators = 0;

    public bool $saved = false;

    /**
     * Mount the platform settings component.
     */
    public function mount(): void
    {
        $platform = PlatformSetting::current();
        $settings = $platform->settings ?? [];

        $this->platform_name = (string) ($settings['platform_name'] ?? 'TravelEngine');
        $this->support_email = (string) ($settings['support_email'] ?? 'admin@emvi.dev');
        $this->commission_percentage = (float) (($settings['commission_rate'] ?? 0.00) * 100);
        $this->guest_service_fee_percentage = (float) (($settings['guest_service_fee_rate'] ?? 0.05) * 100);
        $this->booking_hold_minutes = (int) ($settings['booking_hold_minutes'] ?? 30);
        $this->currency_code = (string) ($settings['currency_code'] ?? 'IDR');
        $this->currency_symbol = (string) ($settings['currency_symbol'] ?? 'Rp');

        // Operator context for the header note
        $this->total_operators = Operator::count();
        $this->approved_operators = Operator::where('status', OperatorStatus::Approved)->count();
    }

    /**
     * Save global platform settings.
     */
    public function updatePlatformSettings(): void
    {
        $validated = $this->validate([
            'platform_name' => ['required', 'string', 'max:255'],
            'support_email' => ['required', 'email', 'max:255'],
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'guest_service_fee_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'booking_hold_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'currency_code' => ['required', 'string', 'max:10'],
            'currency_symbol' => ['required', 'string', 'max:10'],
        ]);

        $platform = PlatformSetting::current();
        $settings = $platform->settings ?? [];

        $settings['platform_name'] = $validated['platform_name'];
        $settings['support_email'] = $validated['support_email'];
        $settings['commission_rate'] = round($validated['commission_percentage'] / 100, 4);
        $settings['guest_service_fee_rate'] = round($validated['guest_service_fee_percentage'] / 100, 4);
        $settings['booking_hold_minutes'] = $validated['booking_hold_minutes'];
        $settings['currency_code'] = strtoupper($validated['currency_code']);
        $settings['currency_symbol'] = $validated['currency_symbol'];

        $platform->update(['settings' => $settings]);

        $this->saved = true;
        $this->dispatch('platform-settings-saved');
    }
}; ?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400">
                    <i class="fa-solid fa-sliders text-lg"></i>
                </span>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Platform Settings') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Configure global platform branding, default commission take-rate, currency, and operational hold policies.') }}
                        <span class="ml-2 text-[11px] font-bold text-purple-600 dark:text-purple-400">
                            &mdash; {{ $approved_operators }}/{{ $total_operators }} {{ __('operators active') }}
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Settings Form -->
    <form wire:submit="updatePlatformSettings" class="w-full space-y-6">
        <!-- Section: Global Platform Parameters -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400 text-xs">
                    <i class="fa-solid fa-globe"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Platform Identity & Global Economics') }}
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Platform Name -->
                <div>
                    <x-label for="platform_name" :value="__('Platform Brand Name')" required />
                    <x-input id="platform_name" wire:model="platform_name" type="text" :error="$errors->has('platform_name')" />
                    <x-input-error :messages="$errors->get('platform_name')" />
                </div>

                <!-- Support Email -->
                <div>
                    <x-label for="support_email" :value="__('Platform Support Email')" required />
                    <x-input id="support_email" wire:model="support_email" type="email" :error="$errors->has('support_email')" />
                    <x-input-error :messages="$errors->get('support_email')" />
                </div>

                <!-- Commission Rate (%) -->
                <div>
                    <x-label for="commission_percentage" :value="__('Platform Commission Take-Rate (%)')" required />
                    <div class="relative">
                        <x-input id="commission_percentage" wire:model="commission_percentage" type="number" step="0.1" min="0" max="100" class="pr-8" :error="$errors->has('commission_percentage')" />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">%</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">{{ __('Default platform revenue cut on customer bookings.') }}</p>
                    <x-input-error :messages="$errors->get('commission_percentage')" />
                </div>

                <!-- Guest Service Fee (%) -->
                <div>
                    <x-label for="guest_service_fee_percentage" :value="__('Guest Service Fee (%) — Added at Checkout')" required />
                    <div class="relative">
                        <x-input id="guest_service_fee_percentage" wire:model="guest_service_fee_percentage" type="number" step="0.1" min="0" max="100" class="pr-8" :error="$errors->has('guest_service_fee_percentage')" />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">%</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">{{ __('Convenience fee added to guest checkout (100% net goes to operator).') }}</p>
                    <x-input-error :messages="$errors->get('guest_service_fee_percentage')" />
                </div>

                <!-- Unpaid Booking Hold Window -->
                <div>
                    <x-label for="booking_hold_minutes" :value="__('Unpaid Hold Timeout (Minutes)')" required />
                    <div class="relative">
                        <x-input id="booking_hold_minutes" wire:model="booking_hold_minutes" type="number" min="5" max="1440" class="pr-12" :error="$errors->has('booking_hold_minutes')" />
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">mins</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">{{ __('Duration availability slots are reserved during pending checkouts.') }}</p>
                    <x-input-error :messages="$errors->get('booking_hold_minutes')" />
                </div>

                <!-- Currency Code -->
                <div>
                    <x-label for="currency_code" :value="__('Default Currency Code')" required />
                    <x-input id="currency_code" wire:model="currency_code" type="text" class="font-mono text-xs uppercase" :error="$errors->has('currency_code')" />
                    <x-input-error :messages="$errors->get('currency_code')" />
                </div>

                <!-- Currency Symbol -->
                <div>
                    <x-label for="currency_symbol" :value="__('Default Currency Symbol')" required />
                    <x-input id="currency_symbol" wire:model="currency_symbol" type="text" :error="$errors->has('currency_symbol')" />
                    <x-input-error :messages="$errors->get('currency_symbol')" />
                </div>
            </div>
        </div>

        <!-- Section: Internal Wiki & Scope Architecture -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-zinc-800">
                <div class="flex items-center gap-2.5">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-xs">
                        <i class="fa-solid fa-book-bookmark"></i>
                    </span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('Platform Scope, Commercial & Financial Specifications Wiki') }}
                    </h3>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                    Rev. 16 Baseline
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <!-- Card 1: V1 Scope & Features -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-950/70 border border-slate-200/70 dark:border-zinc-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-800 dark:text-white flex items-center gap-1.5">
                            <i class="fa-solid fa-file-contract text-indigo-500"></i>
                            V1 Scope & Features Spec
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">Rev. 16</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                        Comprehensive blueprint covering 1-click payment links, WhatsApp dispatch, GSC verification, responsive mobile cards, and AI discovery feeds.
                    </p>
                    <div class="pt-1">
                        <a href="file:///Users/mastervarol/Herd/booking/scope_and_features.md" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            View scope_and_features.md ➔
                        </a>
                    </div>
                </div>

                <!-- Card 2: Commercial & Pricing Model -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-950/70 border border-slate-200/70 dark:border-zinc-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-800 dark:text-white flex items-center gap-1.5">
                            <i class="fa-solid fa-coins text-amber-500"></i>
                            Commercial & Pricing Matrix
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">4 Tiers</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                        Official commercial rules: 0% operator commission, 100% net operator payout, 5% guest checkout fee pass-through, and BYO custom payment keys.
                    </p>
                    <div class="pt-1">
                        <a href="file:///Users/mastervarol/Herd/booking/platform_commercial_and_pricing_model.md" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:underline">
                            View commercial_model.md ➔
                        </a>
                    </div>
                </div>

                <!-- Card 3: Money Rules & Settlement Spec -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-950/70 border border-slate-200/70 dark:border-zinc-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-800 dark:text-white flex items-center gap-1.5">
                            <i class="fa-solid fa-scale-balanced text-emerald-500"></i>
                            Money Rules & Settlement
                        </span>
                        <span class="text-[10px] font-mono text-slate-400">8 States</span>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                        Canonical rules for double-entry ledger entries, 8-state escrow lifecycle, negative balance recovery, chargeback dispute holds, and DOKU reconciliation.
                    </p>
                    <div class="pt-1">
                        <a href="file:///Users/mastervarol/Herd/booking/emvi_v1_money_rules_and_settlement_spec.md" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                            View money_rules_spec.md ➔
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button & Success Toast -->
        <div class="flex items-center gap-4 pt-2">
            <x-button variant="primary" type="submit" data-test="save-platform-settings-button" class="shadow-sm bg-purple-600 hover:bg-purple-700 active:bg-purple-800 text-white">
                <i class="fa-solid fa-floppy-disk mr-1 text-xs"></i>
                {{ __('Save Platform Settings') }}
            </x-button>

            <div x-data="{ shown: false, timeout: null }"
                 x-init="@this.on('platform-settings-updated', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2500); })"
                 x-show.transition.out.opacity.duration.1500ms="shown"
                 x-transition:leave.opacity.duration.1500ms
                 style="display: none;"
                 class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                <i class="fa-solid fa-circle-check"></i>
                {{ __('Platform settings updated successfully.') }}
            </div>
        </div>
    </form>
</div>
