<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\PlatformSetting;
use App\Services\PaymentMatchService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Settings')] #[Layout('layouts.admin')] class extends Component {
    // Global Platform Parameters
    public string $platform_name = 'TravelEngine';
    public string $support_email = 'support@travelengine.online';
    public float $commission_percentage = 0.0; // 0% operator commission
    public float $guest_service_fee_percentage = 5.0; // 5% guest service fee
    public int $booking_hold_minutes = 30;
    public string $currency_code = 'IDR';
    public string $currency_symbol = 'Rp';

    public bool $platform_maintenance = false;

    public bool $confirming_maintenance = false;

    public bool $pending_maintenance = false;

    public int $approved_operators = 0;
    public int $total_operators = 0;

    /**
     * @var list<array{payment_id: string, invoice: string, amount: float, reason: string, found_at: string}>
     */
    public array $unmatched_payments = [];

    public bool $saved = false;

    /**
     * Mount the platform settings component.
     */
    public function mount(): void
    {
        $platform = PlatformSetting::current();
        $settings = $platform->settings ?? [];

        $this->platform_name = (string) ($settings['platform_name'] ?? 'TravelEngine');
        $this->support_email = $platform->getOperatorSupportEmail();
        $this->commission_percentage = (float) (($settings['commission_rate'] ?? 0.0) * 100);
        $this->guest_service_fee_percentage = (float) (($settings['guest_service_fee_rate'] ?? 0.05) * 100);
        $this->booking_hold_minutes = (int) ($settings['booking_hold_minutes'] ?? 30);
        $this->currency_code = (string) ($settings['currency_code'] ?? 'IDR');
        $this->currency_symbol = (string) ($settings['currency_symbol'] ?? 'Rp');
        $this->platform_maintenance = $platform->isPlatformMaintenance();

        // Operator context for the header note
        $this->total_operators = Operator::count();
        $this->approved_operators = Operator::where('status', OperatorStatus::Approved)->count();
        $this->unmatched_payments = $platform->getUnmatchedPayments();
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
        $settings['support_email'] = $platform->isUnusableOperatorSupportEmail($validated['support_email'])
            ? 'support@travelengine.online'
            : $validated['support_email'];
        $settings['commission_rate'] = round($validated['commission_percentage'] / 100, 4);
        $settings['guest_service_fee_rate'] = round($validated['guest_service_fee_percentage'] / 100, 4);
        $settings['booking_hold_minutes'] = $validated['booking_hold_minutes'];
        $settings['currency_code'] = strtoupper($validated['currency_code']);
        $settings['currency_symbol'] = $validated['currency_symbol'];

        $platform->update(['settings' => $settings]);

        $this->saved = true;
        $this->dispatch('platform-settings-saved');
    }

    /**
     * Ask before pausing or resuming storefront checkout.
     */
    public function requestMaintenanceToggle(): void
    {
        $this->pending_maintenance = ! $this->platform_maintenance;
        $this->confirming_maintenance = true;
        $this->dispatch('open-modal', 'confirm-platform-maintenance');
    }

    /**
     * Close the maintenance confirmation without changing state.
     */
    public function cancelMaintenanceToggle(): void
    {
        $this->confirming_maintenance = false;
        $this->dispatch('close-modal', 'confirm-platform-maintenance');
    }

    /**
     * Apply the confirmed maintenance state immediately.
     */
    public function confirmMaintenanceToggle(): void
    {
        if (! $this->confirming_maintenance) {
            return;
        }

        $this->platform_maintenance = $this->pending_maintenance;
        PlatformSetting::current()->setPlatformMaintenance($this->platform_maintenance);
        $this->confirming_maintenance = false;
        $this->dispatch('close-modal', 'confirm-platform-maintenance');
        $this->dispatch('platform-maintenance-toggled');
    }

    /**
     * Scan paid guest charges that never reached an operator wallet.
     */
    public function checkUnmatchedPayments(): void
    {
        $this->unmatched_payments = app(PaymentMatchService::class)->unmatchedPaidCharges();
    }
}; ?>

<div class="space-y-6">
    <x-page-header
        :title="__('Settings')"
        :subtitle="__('Platform name, guest fee, and how long we hold an unpaid spot.')"
        icon="fa-sliders"
    >
        <x-slot:actions>
            <span class="text-xs font-semibold text-op-subtle">
                {{ $approved_operators }}/{{ $total_operators }} {{ __('operators active') }}
            </span>
        </x-slot:actions>
    </x-page-header>

    <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-2.5">
                <span class="p-1.5 rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200/80 dark:border-amber-900/60 text-xs mt-0.5">
                    <i class="fa-solid fa-pause"></i>
                </span>
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('Platform maintenance') }}
                    </h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        @if ($platform_maintenance)
                            {{ __('On. Guests can browse storefronts but cannot book or pay. Operator sign-up is closed. This is not Laravel down — marketing, slugs, and admin stay up.') }}
                        @else
                            {{ __('Off. Storefronts can take bookings. Operator sign-up still follows the registration flag.') }}
                        @endif
                    </p>
                </div>
            </div>
            <button
                type="button"
                wire:click="requestMaintenanceToggle"
                class="relative inline-flex items-center cursor-pointer shrink-0 self-start sm:self-center"
            >
                <span class="sr-only">{{ __('Change platform maintenance') }}</span>
                <span
                    class="w-11 h-6 rounded-full transition-colors {{ $platform_maintenance ? 'bg-amber-500' : 'bg-slate-200 dark:bg-zinc-700' }}"
                >
                    <span
                        class="absolute top-[2px] left-[2px] h-5 w-5 rounded-full bg-white border border-slate-300 dark:border-zinc-600 transition-transform {{ $platform_maintenance ? 'translate-x-5' : '' }}"
                    ></span>
                </span>
                <span class="ml-3 text-xs font-semibold text-slate-700 dark:text-slate-300">
                    {{ $platform_maintenance ? __('On') : __('Off') }}
                </span>
            </button>
        </div>
    </div>

    <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4">
        <div class="flex flex-col gap-3 pb-2 border-b border-slate-100 dark:border-[#1e2433] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2.5">
                <span class="p-1.5 rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 text-xs">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Paid, but not in a wallet yet') }}
                </h3>
            </div>
            <x-button type="button" variant="secondary" size="sm" wire:click="checkUnmatchedPayments" wire:loading.attr="disabled">
                <i class="fa-solid fa-rotate text-xs" wire:loading.class="animate-spin" wire:target="checkUnmatchedPayments"></i>
                <span>{{ __('Check again') }}</span>
            </x-button>
        </div>

        @if ($unmatched_payments === [])
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Every guest payment we checked already reached an operator wallet.') }}
            </p>
        @else
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('These guests paid, but the operator wallet was not credited. Someone on the team should look at each one.') }}
            </p>
            <ul class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                @foreach ($unmatched_payments as $item)
                    <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $item['invoice'] }}</p>
                            <p class="text-xs text-slate-500">{{ $item['reason'] }}</p>
                        </div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">
                            Rp {{ number_format((float) $item['amount'], 0, ',', '.') }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <!-- Main Settings Form -->
    <form wire:submit="updatePlatformSettings" class="w-full space-y-6">
        <!-- Section: Global Platform Parameters -->
        <div
            class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-5">
            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-[#1e2433]">
                <span
                    class="p-1.5 rounded-lg bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30 text-xs">
                    <i class="fa-solid fa-globe"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Name, fees, and currency') }}
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Platform Name -->
                <div>
                    <x-label for="platform_name" :value="__('Platform name')" required />
                    <x-input id="platform_name" wire:model="platform_name" type="text" :error="$errors->has('platform_name')" />
                    <x-input-error :messages="$errors->get('platform_name')" />
                </div>

                <!-- Support Email -->
                <div>
                    <x-label for="support_email" :value="__('Operator support email')" required />
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 mb-1.5">
                        {{ __('Operators write here for billing, plans, and account help.') }}
                    </p>
                    <x-input id="support_email" wire:model="support_email" type="email" :error="$errors->has('support_email')" />
                    <x-input-error :messages="$errors->get('support_email')" />
                </div>

                <!-- Commission Rate (%) -->
                <div>
                    <x-label for="commission_percentage" :value="__('Cut from the listed price (%)')" required />
                    <div class="relative">
                        <x-input id="commission_percentage" wire:model="commission_percentage" type="number"
                            step="0.1" min="0" max="100" class="pr-8" :error="$errors->has('commission_percentage')" />
                        <span
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">%</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">
                        {{ __('Usually 0. The operator still gets the listed price.') }}</p>
                    <x-input-error :messages="$errors->get('commission_percentage')" />
                </div>

                <!-- Guest Service Fee (%) -->
                <div>
                    <x-label for="guest_service_fee_percentage" :value="__('Guest fee at checkout (%)')" required />
                    <div class="relative">
                        <x-input id="guest_service_fee_percentage" wire:model="guest_service_fee_percentage"
                            type="number" step="0.1" min="0" max="100" class="pr-8"
                            :error="$errors->has('guest_service_fee_percentage')" />
                        <span
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">%</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">
                        {{ __('Added on top of the listed price. The operator still gets 100% of that listed price.') }}</p>
                    <x-input-error :messages="$errors->get('guest_service_fee_percentage')" />
                </div>

                <!-- Unpaid Booking Hold Window -->
                <div>
                    <x-label for="booking_hold_minutes" :value="__('Hold an unpaid spot for (minutes)')" required />
                    <div class="relative">
                        <x-input id="booking_hold_minutes" wire:model="booking_hold_minutes" type="number"
                            min="5" max="1440" class="pr-12" :error="$errors->has('booking_hold_minutes')" />
                        <span
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">mins</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1">
                        {{ __('How long we keep a spot while the guest pays.') }}</p>
                    <x-input-error :messages="$errors->get('booking_hold_minutes')" />
                </div>

                <!-- Currency Code -->
                <div>
                    <x-label for="currency_code" :value="__('Currency')" required />
                    <x-input id="currency_code" wire:model="currency_code" type="text"
                        class="font-mono text-xs uppercase" :error="$errors->has('currency_code')" />
                    <x-input-error :messages="$errors->get('currency_code')" />
                </div>

                <!-- Currency Symbol -->
                <div>
                    <x-label for="currency_symbol" :value="__('Currency symbol')" required />
                    <x-input id="currency_symbol" wire:model="currency_symbol" type="text" :error="$errors->has('currency_symbol')" />
                    <x-input-error :messages="$errors->get('currency_symbol')" />
                </div>
            </div>
        </div>

        <!-- Submit Button & Success Toast -->
        <div class="flex items-center gap-4 pt-2">
            <x-button type="submit" data-test="save-platform-settings-button">
                <i class="fa-solid fa-floppy-disk text-xs"></i>
                {{ __('Save') }}
            </x-button>

            <div x-data="{ shown: false, timeout: null }" x-init="@this.on('platform-settings-saved', () => {
                clearTimeout(timeout);
                shown = true;
                timeout = setTimeout(() => { shown = false }, 2500);
            })"
                x-show.transition.out.opacity.duration.1500ms="shown" x-transition:leave.opacity.duration.1500ms
                style="display: none;"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                <i class="fa-solid fa-circle-check"></i>
                {{ __('Platform settings updated successfully.') }}
            </div>
        </div>
    </form>

    <x-modal name="confirm-platform-maintenance" :show="$confirming_maintenance" maxWidth="md">
        <div class="p-6 space-y-4 text-center">
            <div class="w-12 h-12 rounded-2xl {{ $pending_maintenance ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/80 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300' }} flex items-center justify-center mx-auto text-lg">
                <i class="fa-solid {{ $pending_maintenance ? 'fa-pause' : 'fa-play' }}"></i>
            </div>

            <div class="space-y-1.5">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ $pending_maintenance ? __('Turn on platform maintenance?') : __('Turn off platform maintenance?') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                    @if ($pending_maintenance)
                        {{ __('Guests on every storefront will not be able to book, pay, or cancel. Operator sign-up will close. Catalogs, operator log in, and admin stay up. This is not Laravel down.') }}
                    @else
                        {{ __('Storefronts will take bookings and payments again. Operator sign-up still follows the registration flag.') }}
                    @endif
                </p>
            </div>

            <div class="flex items-center justify-center gap-3 pt-3">
                <x-button
                    type="button"
                    variant="secondary"
                    wire:click="cancelMaintenanceToggle"
                    class="font-semibold text-xs"
                >
                    {{ __('Cancel') }}
                </x-button>
                <x-button
                    type="button"
                    variant="{{ $pending_maintenance ? 'danger' : 'primary' }}"
                    wire:click="confirmMaintenanceToggle"
                    class="font-semibold text-xs shadow-xs"
                >
                    {{ $pending_maintenance ? __('Pause bookings') : __('Resume bookings') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
