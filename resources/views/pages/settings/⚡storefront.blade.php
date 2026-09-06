<?php

use App\Models\Operator;
use App\Concerns\ResolvesCurrentOperator;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Storefront Settings')] class extends Component {
    use ResolvesCurrentOperator;
    // Storefront Sales & Inventory Rules
    public bool $allow_standalone_products = true;
    public bool $show_reviews = true;
    public bool $show_inclusions_preview = true;
    public string $booking_confirmation_mode = 'automatic'; // 'automatic' (instant) or 'manual' (operator review)

    // Hero Customizations
    public string $hero_headline = '';
    public string $hero_tagline = '';

    // Policies and Terms
    public string $terms_and_conditions = '';

    public bool $saved = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if ($operator) {
            $this->terms_and_conditions = $operator->terms_and_conditions ?? '';

            $settings = $operator->settings ?? [];
            $storefrontSettings = $settings['storefront'] ?? [];

            $this->allow_standalone_products = (bool) ($storefrontSettings['allow_standalone_products'] ?? true);
            $this->show_reviews = (bool) ($storefrontSettings['show_reviews'] ?? true);
            $this->show_inclusions_preview = (bool) ($storefrontSettings['show_inclusions_preview'] ?? true);
            $this->booking_confirmation_mode = (string) ($storefrontSettings['booking_confirmation_mode'] ?? 'automatic');
            $this->hero_headline = (string) ($storefrontSettings['hero_headline'] ?? '');
            $this->hero_tagline = (string) ($storefrontSettings['hero_tagline'] ?? '');
        }
    }

    /**
     * Update storefront sales rules and guest policies.
     */
    public function updateStorefrontSettings(): void
    {
        $validated = $this->validate([
            'allow_standalone_products' => ['boolean'],
            'show_reviews' => ['boolean'],
            'show_inclusions_preview' => ['boolean'],
            'booking_confirmation_mode' => ['required', 'in:automatic,manual'],
            'hero_headline' => ['nullable', 'string', 'max:255'],
            'hero_tagline' => ['nullable', 'string', 'max:500'],
            'terms_and_conditions' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if ($operator) {
            $settings = $operator->settings ?? [];
            $settings['storefront'] = [
                'allow_standalone_products' => $this->allow_standalone_products,
                'show_reviews' => $this->show_reviews,
                'show_inclusions_preview' => $this->show_inclusions_preview,
                'booking_confirmation_mode' => $this->booking_confirmation_mode,
                'hero_headline' => $validated['hero_headline'] ?? null,
                'hero_tagline' => $validated['hero_tagline'] ?? null,
            ];

            $operator->update([
                'terms_and_conditions' => $validated['terms_and_conditions'] ?? null,
                'settings' => $settings,
            ]);
        }

        $this->saved = true;
        $this->dispatch('storefront-updated');
    }
}; ?>

<div class="space-y-6 w-full">
    <!-- Desktop Notice on Mobile -->
    <x-desktop-only-notice :title="__('Storefront Policies Best Managed on Desktop')" :description="__(
        'Configuring cancellation rules, selling permissions, and comprehensive terms & conditions is best performed on desktop.',
    )" />

    <div class="hidden lg:block space-y-6">
        <!-- Unified Settings Navigation -->
        <x-settings-nav />

        <!-- Standalone Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="p-2 rounded-xl bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-300">
                        <i class="fa-solid fa-store text-lg"></i>
                    </span>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Storefront & Policies') }}
                    </h1>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Control standalone product selling permissions, catalog visibility, and guest booking policies.') }}
                </p>
            </div>
        </div>

        <!-- Main Settings Form -->
        <form wire:submit="updateStorefrontSettings" class="w-full space-y-6">
            <!-- Card 1: Sales Permissions & Catalog Configuration -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                    <span
                        class="p-1.5 rounded-lg bg-[#FFEF4D] text-[#090d16] dark:bg-indigo-950/70 dark:text-indigo-400 text-xs">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('Sales Permissions & Selling Mode') }}
                    </h3>
                </div>

                <div class="space-y-3">
                    <!-- Toggle: Allow Standalone Product Selling -->
                    <div
                        class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200 dark:border-zinc-800">
                        <x-checkbox id="allow_standalone_products" wire:model="allow_standalone_products"
                            :label="__('Allow Standalone Product & Service Sales')" :description="__(
                                'When enabled, products and services flagged as \'Sell Standalone\' (e.g. day passes, single sessions, guide hire) can be booked directly by guests outside of packages.',
                            )" />
                    </div>

                    <!-- Toggle: Show Reviews Section -->
                    <div
                        class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200 dark:border-zinc-800">
                        <x-checkbox id="show_reviews" wire:model="show_reviews" :label="__('Display Verified Guest Reviews on Storefront')" :description="__(
                            'Showcase authentic guest ratings, comments, and experience feedback on your public landing page.',
                        )" />
                    </div>

                    <!-- Toggle: Show Inclusions Preview -->
                    <div
                        class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200 dark:border-zinc-800">
                        <x-checkbox id="show_inclusions_preview" wire:model="show_inclusions_preview" :label="__('Show Package Inclusions Preview Chips')"
                            :description="__(
                                'Display included product badges directly on package listing cards in catalog view.',
                            )" />
                    </div>
                </div>
            </div>

            <!-- Card 2: Booking Confirmation Workflow Mode -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                    <span
                        class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 text-xs">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Booking Confirmation & Approval Workflow') }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Choose whether paid guest bookings are confirmed instantly or require manual operator review.') }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Option 1: Automatic / Instant Booking -->
                    <label
                        class="relative p-5 rounded-2xl border-2 cursor-pointer transition-all duration-150 flex flex-col justify-between space-y-3 {{ $booking_confirmation_mode === 'automatic' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30 ring-2 ring-indigo-600/20' : 'border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 hover:border-slate-300 dark:hover:border-zinc-700' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-950/80 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm shrink-0">
                                    <i class="fa-solid fa-bolt"></i>
                                </span>
                                <div>
                                    <h4 class="font-bold text-sm text-slate-900 dark:text-white">
                                        {{ __('Instant Booking') }}
                                    </h4>
                                    <span
                                        class="text-[10px] font-bold uppercase text-emerald-600 dark:text-emerald-400 tracking-wider">
                                        {{ __('Automated (Recommended)') }}
                                    </span>
                                </div>
                            </div>
                            <input type="radio" name="booking_confirmation_mode" value="automatic"
                                wire:model.live="booking_confirmation_mode"
                                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 mt-1" />
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            {{ __('Bookings are instantly confirmed as soon as payment is settled. Calendar capacity is locked automatically and vouchers are immediately issued to guests.') }}
                        </p>
                    </label>

                    <!-- Option 2: Manual Confirmation / Operator Review -->
                    <label
                        class="relative p-5 rounded-2xl border-2 cursor-pointer transition-all duration-150 flex flex-col justify-between space-y-3 {{ $booking_confirmation_mode === 'manual' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30 ring-2 ring-indigo-600/20' : 'border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 hover:border-slate-300 dark:hover:border-zinc-700' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-9 h-9 rounded-xl bg-indigo-100 dark:bg-indigo-950/80 text-amber-600 dark:text-indigo-400 flex items-center justify-center text-sm shrink-0">
                                    <i class="fa-solid fa-user-check"></i>
                                </span>
                                <div>
                                    <h4 class="font-bold text-sm text-slate-900 dark:text-white">
                                        {{ __('Manual Approval') }}
                                    </h4>
                                    <span
                                        class="text-[10px] font-bold uppercase text-indigo-600 dark:text-indigo-400 tracking-wider">
                                        {{ __('Operator Review') }}
                                    </span>
                                </div>
                            </div>
                            <input type="radio" name="booking_confirmation_mode" value="manual"
                                wire:model.live="booking_confirmation_mode"
                                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 mt-1" />
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            {{ __('Paid reservations are placed in "Pending Confirmation" status. You manually inspect staff schedule, capacity, and resource availability before clicking "Confirm" in your Bookings dashboard.') }}
                        </p>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('booking_confirmation_mode')" />
            </div>

            <!-- Card 3: Hero Banner Content -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                    <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/70 text-sky-600 dark:text-sky-400 text-xs">
                        <i class="fa-solid fa-bullhorn"></i>
                    </span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('Hero Banner Copy & Marketing Text') }}
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-label for="hero_headline" :value="__('Custom Hero Headline (Optional)')" />
                        <x-input id="hero_headline" wire:model="hero_headline" type="text"
                            placeholder="e.g. Unforgettable Bali Expeditions" :error="$errors->has('hero_headline')" />
                        <p class="text-[11px] text-slate-500 mt-1">
                            {{ __('Leave blank to use your business name automatically.') }}</p>
                        <x-input-error :messages="$errors->get('hero_headline')" />
                    </div>

                    <div>
                        <x-label for="hero_tagline" :value="__('Custom Hero Tagline (Optional)')" />
                        <x-input id="hero_tagline" wire:model="hero_tagline" type="text"
                            placeholder="e.g. Direct bookings & guaranteed private tours" :error="$errors->has('hero_tagline')" />
                        <x-input-error :messages="$errors->get('hero_tagline')" />
                    </div>
                </div>
            </div>

            <!-- Card 3: Storefront Terms & Policies -->
            <div
                class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                    <span
                        class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/70 text-amber-600 dark:text-amber-400 text-xs">
                        <i class="fa-solid fa-file-contract"></i>
                    </span>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('Storefront Booking Policy & Cancellation Rules') }}
                    </h3>
                </div>

                <div class="space-y-2">
                    <x-label for="terms_and_conditions" :value="__('Official Guest Booking Terms (Displayed on dedicated /terms page)')" required />
                    <x-textarea id="terms_and_conditions" wire:model="terms_and_conditions" rows="5"
                        placeholder="Detail your standard policies: cancellation cutoff rules, health and physical requirements, weather rescheduling policies, and guest liability disclaimers..."
                        :error="$errors->has('terms_and_conditions')" />
                    <p class="text-[11px] text-slate-500">
                        {{ __('These terms are frozen into the guest reservation snapshot upon booking.') }}</p>
                    <x-input-error :messages="$errors->get('terms_and_conditions')" />
                </div>
            </div>

            <!-- Submit Button & Success Toast -->
            <div class="flex items-center gap-4 pt-2">
                <x-button variant="primary" type="submit" data-test="update-storefront-button" class="shadow-sm">
                    <i class="fa-solid fa-floppy-disk mr-1 text-xs"></i>
                    {{ __('Save Storefront Settings') }}
                </x-button>

                <div x-data="{ shown: false, timeout: null }" x-init="@this.on('storefront-updated', () => {
                    clearTimeout(timeout);
                    shown = true;
                    timeout = setTimeout(() => { shown = false }, 2500);
                })"
                    x-show.transition.out.opacity.duration.1500ms="shown" x-transition:leave.opacity.duration.1500ms
                    style="display: none;"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    <i class="fa-solid fa-circle-check"></i>
                    {{ __('Storefront settings saved successfully.') }}
                </div>
            </div>
        </form>
    </div>
</div>
