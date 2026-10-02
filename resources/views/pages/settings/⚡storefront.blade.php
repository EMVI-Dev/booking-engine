<?php

use App\Models\Operator;
use App\Concerns\ResolvesCurrentOperator;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Storefront Settings')] class extends Component {
    use ResolvesCurrentOperator;
    // Storefront Sales & Inventory Rules
    public bool $allow_standalone_products = true;
    public bool $show_inclusions_preview = true;
    public string $booking_confirmation_mode = 'automatic'; // 'automatic' (instant) or 'manual' (operator review)

    // Hero Customizations
    public string $hero_headline = '';
    public string $hero_tagline = '';

    // Policies and Terms
    public string $terms_and_conditions = '';

    public bool $saved = false;

    public bool $highlightTerms = false;

    public bool $highlightHero = false;

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
            $this->show_inclusions_preview = (bool) ($storefrontSettings['show_inclusions_preview'] ?? true);
            $this->booking_confirmation_mode = (string) ($storefrontSettings['booking_confirmation_mode'] ?? 'automatic');
            $this->hero_headline = (string) ($storefrontSettings['hero_headline'] ?? '');
            $this->hero_tagline = (string) ($storefrontSettings['hero_tagline'] ?? '');
        }

        $this->syncSetupHighlights();
    }

    protected function syncSetupHighlights(): void
    {
        $this->highlightTerms = blank(trim($this->terms_and_conditions));
        $this->syncHeroHighlight();
    }

    protected function syncHeroHighlight(): void
    {
        $this->highlightHero = blank(trim($this->hero_headline)) && blank(trim($this->hero_tagline));
    }

    /**
     * Update storefront sales rules and guest policies.
     */
    public function updateStorefrontSettings(): void
    {
        $validated = $this->validate([
            'allow_standalone_products' => ['boolean'],
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
                'show_inclusions_preview' => $this->show_inclusions_preview,
                'booking_confirmation_mode' => $this->booking_confirmation_mode,
                'hero_headline' => $validated['hero_headline'] ?? null,
                'hero_tagline' => $validated['hero_tagline'] ?? null,
            ];

            $operator->update([
                'terms_and_conditions' => $validated['terms_and_conditions'] ?? null,
                'settings' => $settings,
            ]);

            $this->syncSetupHighlights();
        }

        $this->saved = true;
        $this->dispatch('storefront-updated');
        $this->dispatch('setup-progress-updated');
        $this->dispatch('toast', message: __('Storefront settings saved.'), type: 'success');
    }
}; ?>

<div class="space-y-6 w-full">
    <div class="space-y-6">
        <!-- Unified Settings Navigation -->
        <x-settings-nav />

        <x-page-header
            :title="__('Storefront & Policies')"
            :subtitle="__('Control standalone product selling permissions, catalog visibility, and guest booking policies.')"
            icon="fa-store"
        />

        <!-- Main Settings Form -->
        <form wire:submit="updateStorefrontSettings" class="w-full space-y-6">
            <!-- Card 1: Sales Permissions & Catalog Configuration -->
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-5">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-[8px] bg-[#F4F5F7] dark:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] text-xs">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </span>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7]">
                        {{ __('Sales Permissions & Selling Mode') }}
                    </h3>
                </div>

                <div class="space-y-3">
                    <!-- Toggle: Allow Standalone Product Selling -->
                    <div
                        class="p-4 rounded-[8px] bg-[#F9FAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]">
                        <x-checkbox id="allow_standalone_products" wire:model="allow_standalone_products"
                            :label="__('Allow Standalone Product & Service Sales')" :description="__(
                                'When enabled, products and services flagged as \'Sell Standalone\' (e.g. day passes, single sessions, guide hire) can be booked directly by guests outside of packages.',
                            )" />
                    </div>

                    <!-- Toggle: Show Inclusions Preview -->
                    <div
                        class="p-4 rounded-[8px] bg-[#F9FAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]">
                        <x-checkbox id="show_inclusions_preview" wire:model="show_inclusions_preview" :label="__('Show Package Inclusions Preview Chips')"
                            :description="__(
                                'Display included product badges directly on package listing cards in catalog view.',
                            )" />
                    </div>
                </div>
            </div>

            <!-- Card 2: Booking Confirmation Workflow Mode -->
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-5">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-[8px] bg-[#F4F5F7] dark:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] text-xs">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </span>
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7]">
                            {{ __('Booking Confirmation & Approval Workflow') }}
                        </h3>
                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                            {{ __('Choose whether paid guest bookings are confirmed instantly or require manual operator review.') }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Option 1: Automatic / Instant Booking -->
                    <label
                        class="relative p-4 sm:p-5 rounded-[8px] border cursor-pointer transition-all duration-150 flex flex-col justify-between space-y-3 {{ $booking_confirmation_mode === 'automatic' ? 'border-[#12181E] dark:border-white bg-[#F9FAFB] dark:bg-[#141821] ring-1 ring-[#12181E] dark:ring-white' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] hover:border-[#12181E]/30 dark:hover:border-white/30' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-[6px] bg-[#FFEF4D] text-[#12181E] text-xs">
                                    <i class="fa-solid fa-bolt"></i>
                                </span>
                                <div>
                                    <h4 class="font-semibold text-xs sm:text-sm text-[#12181E] dark:text-white">
                                        {{ __('Instant Booking') }}
                                    </h4>
                                    <span
                                        class="text-[10px] font-semibold uppercase text-emerald-600 dark:text-emerald-400 tracking-wider">
                                        {{ __('Automated (Recommended)') }}
                                    </span>
                                </div>
                            </div>
                            <input type="radio" name="booking_confirmation_mode" value="automatic"
                                wire:model.live="booking_confirmation_mode"
                                class="w-4 h-4 accent-[#12181E] dark:accent-[#FFEF4D] mt-1" />
                        </div>
                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">
                            {{ __('Bookings are instantly confirmed as soon as payment is settled. Calendar capacity is locked automatically and vouchers are immediately issued to guests.') }}
                        </p>
                    </label>

                    <!-- Option 2: Manual Confirmation / Operator Review -->
                    <label
                        class="relative p-4 sm:p-5 rounded-[8px] border cursor-pointer transition-all duration-150 flex flex-col justify-between space-y-3 {{ $booking_confirmation_mode === 'manual' ? 'border-[#12181E] dark:border-white bg-[#F9FAFB] dark:bg-[#141821] ring-1 ring-[#12181E] dark:ring-white' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] hover:border-[#12181E]/30 dark:hover:border-white/30' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-8 shrink-0 items-center justify-center rounded-[6px] bg-[#F4F5F7] dark:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] text-xs">
                                    <i class="fa-solid fa-user-check"></i>
                                </span>
                                <div>
                                    <h4 class="font-semibold text-xs sm:text-sm text-[#12181E] dark:text-white">
                                        {{ __('Manual Approval') }}
                                    </h4>
                                    <span
                                        class="text-[10px] font-semibold uppercase text-[#5A6578] dark:text-[#9DA4B2] tracking-wider">
                                        {{ __('Operator Review') }}
                                    </span>
                                </div>
                            </div>
                            <input type="radio" name="booking_confirmation_mode" value="manual"
                                wire:model.live="booking_confirmation_mode"
                                class="w-4 h-4 accent-[#12181E] dark:accent-[#FFEF4D] mt-1" />
                        </div>
                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">
                            {{ __('Paid reservations are placed in "Pending Confirmation" status. You manually inspect staff schedule, capacity, and resource availability before clicking "Confirm" in your Bookings dashboard.') }}
                        </p>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('booking_confirmation_mode')" />
            </div>

            <!-- Card 3: Hero Banner Content -->
            <x-setup-needed :needed="$highlightHero" anchor="setup-hero">
                <div class="space-y-4 rounded-[12px] border border-[#E4E5E9] bg-white p-5 sm:p-6 shadow-none dark:border-[#1E2433] dark:bg-[#10141d]">
                    <div class="flex items-center gap-2.5 pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-[8px] bg-[#F4F5F7] dark:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] text-xs">
                            <i class="fa-solid fa-bullhorn"></i>
                        </span>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7]">
                            {{ __('Hero Banner Copy & Marketing Text') }}
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-label for="hero_headline" :value="__('Custom Hero Headline')" required />
                            <x-input id="hero_headline" wire:model.live.debounce.300ms="hero_headline" type="text"
                                placeholder="e.g. Unforgettable Bali Expeditions" :error="$errors->has('hero_headline')" />
                            <p class="mt-1 text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">
                                {{ __('Shown at the top of your booking page. Add a headline or tagline.') }}</p>
                            <x-input-error :messages="$errors->get('hero_headline')" />
                        </div>

                        <div>
                            <x-label for="hero_tagline" :value="__('Custom Hero Tagline')" />
                            <x-input id="hero_tagline" wire:model.live.debounce.300ms="hero_tagline" type="text"
                                placeholder="e.g. Direct bookings & guaranteed private tours" :error="$errors->has('hero_tagline')" />
                            <x-input-error :messages="$errors->get('hero_tagline')" />
                        </div>
                    </div>
                </div>
            </x-setup-needed>

            <!-- Card 4: Storefront Terms & Policies -->
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-5">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-[8px] bg-[#F4F5F7] dark:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] text-xs">
                        <i class="fa-solid fa-file-contract"></i>
                    </span>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7]">
                        {{ __('Storefront Booking Policy & Cancellation Rules') }}
                    </h3>
                </div>

                <div
                    class="space-y-2"
                    x-data
                    x-init="
                        $nextTick(() => {
                            const hash = window.location.hash;
                            const target = hash ? document.querySelector(hash) : null;
                            if (target) {
                                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        })
                    "
                >
                    <x-setup-needed :needed="$highlightTerms" anchor="setup-terms" @class(['space-y-1', 'p-3 sm:p-3.5' => $highlightTerms])>
                        <x-label for="terms_and_conditions" :value="__('Official Guest Booking Terms (Displayed on dedicated /terms page)')" required />
                        <x-textarea id="terms_and_conditions" wire:model.live.debounce.300ms="terms_and_conditions" rows="5"
                            placeholder="Detail your standard policies: cancellation cutoff rules, health and physical requirements, weather rescheduling policies, and guest liability disclaimers..."
                            :error="$errors->has('terms_and_conditions')" />
                        <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __('These terms are frozen into the guest reservation snapshot upon booking.') }}</p>
                        <x-input-error :messages="$errors->get('terms_and_conditions')" />
                    </x-setup-needed>
                </div>
            </div>

            <!-- Submit Button & Success Toast -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center pt-2">
                <x-button variant="primary" type="submit" data-test="update-storefront-button" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="updateStorefrontSettings">
                    <i class="fa-solid fa-floppy-disk mr-1.5 text-xs" wire:loading.remove wire:target="updateStorefrontSettings"></i>
                    <i class="fa-solid fa-spinner fa-spin mr-1.5 text-xs" wire:loading wire:target="updateStorefrontSettings"></i>
                    <span wire:loading.remove wire:target="updateStorefrontSettings">{{ __('Save Storefront Settings') }}</span>
                    <span wire:loading wire:target="updateStorefrontSettings">{{ __('Saving…') }}</span>
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
