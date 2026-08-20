<?php

use App\Enums\AgentStatus;
use App\Enums\ListingStatus;
use App\Models\Agent;
use App\Models\Package;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Reservation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Platform Settings')] #[Layout('layouts.admin')] class extends Component {
    // Global Platform Parameters
    public string $platform_name = 'Emvi Booking Platform';
    public string $support_email = 'admin@emvi.dev';
    public float $commission_percentage = 10.0; // 10%
    public int $booking_hold_minutes = 30;
    public string $currency_code = 'IDR';
    public string $currency_symbol = 'Rp';

    // Dashboard Overview Counts
    public int $total_agents = 0;
    public int $approved_agents = 0;
    public int $total_packages = 0;
    public int $total_products = 0;
    public int $total_reservations = 0;

    public bool $saved = false;

    /**
     * Mount the platform settings component.
     */
    public function mount(): void
    {
        $platform = PlatformSetting::current();
        $settings = $platform->settings ?? [];

        $this->platform_name = (string) ($settings['platform_name'] ?? 'Emvi Booking Platform');
        $this->support_email = (string) ($settings['support_email'] ?? 'admin@emvi.dev');
        $this->commission_percentage = (float) (($settings['commission_rate'] ?? 0.10) * 100);
        $this->booking_hold_minutes = (int) ($settings['booking_hold_minutes'] ?? 30);
        $this->currency_code = (string) ($settings['currency_code'] ?? 'IDR');
        $this->currency_symbol = (string) ($settings['currency_symbol'] ?? 'Rp');

        // Load Platform Overview Stats
        $this->total_agents = Agent::count();
        $this->approved_agents = Agent::where('status', AgentStatus::Approved)->count();
        $this->total_packages = Package::where('status', ListingStatus::Published)->count();
        $this->total_products = Product::where('status', ListingStatus::Published)->count();
        $this->total_reservations = Reservation::count();
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
            'booking_hold_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'currency_code' => ['required', 'string', 'max:10'],
            'currency_symbol' => ['required', 'string', 'max:10'],
        ]);

        $platform = PlatformSetting::current();
        $settings = $platform->settings ?? [];

        $settings['platform_name'] = $validated['platform_name'];
        $settings['support_email'] = $validated['support_email'];
        $settings['commission_rate'] = (float) ($validated['commission_percentage'] / 100);
        $settings['booking_hold_minutes'] = $validated['booking_hold_minutes'];
        $settings['currency_code'] = $validated['currency_code'];
        $settings['currency_symbol'] = $validated['currency_symbol'];

        $platform->update(['settings' => $settings]);

        $this->saved = true;
        $this->dispatch('platform-settings-updated');
    }
}; ?>

<div class="space-y-6 max-w-6xl mx-auto">
    <!-- Header -->
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
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Registered Agents') }}</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900 dark:text-white">{{ $approved_agents }}</span>
                <span class="text-xs text-slate-400">/ {{ $total_agents }} total</span>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Published Packages') }}</span>
            <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $total_packages }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Products & Services') }}</span>
            <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $total_products }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('Total Reservations') }}</span>
            <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $total_reservations }}</div>
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
