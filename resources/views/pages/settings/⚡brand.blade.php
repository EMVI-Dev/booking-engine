<?php

use App\Models\Operator;
use App\Concerns\ResolvesCurrentOperator;
use App\Concerns\UsesMediaStore;
use App\Services\CustomDomainService;
use App\Services\MediaStore;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Brand Settings')] class extends Component {
    use WithFileUploads;
    use ResolvesCurrentOperator;
    use UsesMediaStore;

    // Brand Logo
    public $logo;
    #[Locked]
    public ?string $existing_logo_path = null;

    // Business & Brand identity fields
    public string $agency_name = '';
    public string $bio = '';
    public string $brand_color = '#4f46e5';
    public string $reservation_code_prefix = 'RSV';

    // WhatsApp Storefront Integration & Schedule
    public string $contact_whatsapp = '';
    public string $whatsapp_prefilled_message = '';
    public string $whatsapp_schedule_mode = 'schedule'; // schedule, always
    public string $whatsapp_timezone = 'Asia/Makassar'; // Default UTC+8
    public string $whatsapp_start_time = '08:00';
    public string $whatsapp_end_time = '18:00';
    /** @var array<int, string> */
    public array $whatsapp_days = ['mon', 'tue', 'wed', 'thu', 'fri'];

    // Social Media Links
    public string $instagram_url = '';
    public string $facebook_url = '';
    public string $tiktok_url = '';
    public string $youtube_url = '';

    // Custom Domain (Agency and above)
    public string $custom_domain = '';

    // Marketing & Tracking Pixels
    public string $google_analytics_id = '';
    public string $meta_pixel_id = '';
    public string $google_tag_manager_id = '';
    public string $google_site_verification = '';

    // Notification Channels
    public string $booking_notification_email = '';
    public string $billing_email = '';

    public bool $saved = false;

    public bool $highlightBio = false;

    public bool $highlightLogo = false;

    public bool $highlightBookingNotificationEmail = false;

    public bool $highlightBillingEmail = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorizeAbility('manageSettings');

        $user = Auth::user();

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if ($operator) {
            $this->agency_name = $operator->name;
            $this->bio = $operator->bio ?? '';
            $this->contact_whatsapp = $operator->contact_whatsapp ?? '';
            $this->brand_color = $operator->brand_color ?? '#4f46e5';
            $this->reservation_code_prefix = $operator->reservationCodePrefix();
            $this->existing_logo_path = $operator->logo_path;
            $this->booking_notification_email = $operator->booking_notification_email ?? $user->email;
            $this->billing_email = $operator->billing_email ?? $user->email;

            $settings = $operator->settings ?? [];
            $this->whatsapp_prefilled_message = (string) ($settings['whatsapp_prefilled_message'] ?? 'Hi ' . $operator->name . ', I would like to inquire about your packages.');

            $waSchedule = $settings['whatsapp_schedule'] ?? [];
            $this->whatsapp_schedule_mode = (string) ($waSchedule['mode'] ?? 'schedule');
            $this->whatsapp_timezone = (string) ($waSchedule['timezone'] ?? 'Asia/Makassar');
            $this->whatsapp_start_time = (string) ($waSchedule['start_time'] ?? '08:00');
            $this->whatsapp_end_time = (string) ($waSchedule['end_time'] ?? '18:00');
            $this->whatsapp_days = (array) ($waSchedule['days'] ?? ['mon', 'tue', 'wed', 'thu', 'fri']);

            $social = $settings['social_links'] ?? [];
            $this->instagram_url = (string) ($social['instagram'] ?? '');
            $this->facebook_url = (string) ($social['facebook'] ?? '');
            $this->tiktok_url = (string) ($social['tiktok'] ?? '');
            $this->youtube_url = (string) ($social['youtube'] ?? '');

            $tracking = $settings['tracking'] ?? [];
            $this->google_analytics_id = (string) ($tracking['google_analytics_id'] ?? '');
            $this->meta_pixel_id = (string) ($tracking['meta_pixel_id'] ?? '');
            $this->google_tag_manager_id = (string) ($tracking['google_tag_manager_id'] ?? '');
            $this->google_site_verification = (string) ($tracking['google_site_verification'] ?? '');

            $this->custom_domain = (string) app(CustomDomainService::class)->currentFor($operator)?->domain;
        } else {
            $this->booking_notification_email = $user->email;
            $this->billing_email = $user->email;
        }

        $this->syncSetupHighlights($operator);
    }

    /**
     * Mark which sales-setup fields still need attention on this page.
     * Highlights clear only after a successful save (or logo remove).
     */
    protected function syncSetupHighlights(?Operator $operator): void
    {
        $this->highlightLogo = blank($this->logo) && blank($this->existing_logo_path);
        $this->highlightBio = blank(trim($this->bio));
        $this->highlightBookingNotificationEmail = blank($operator?->booking_notification_email);
        $this->highlightBillingEmail = blank($operator?->billing_email);
    }

    /**
     * Toggle a day in the active WhatsApp operating schedule.
     */
    public function toggleDay(string $day): void
    {
        if (in_array($day, $this->whatsapp_days, true)) {
            $this->whatsapp_days = array_values(array_filter($this->whatsapp_days, fn ($d) => $d !== $day));
        } else {
            $this->whatsapp_days[] = $day;
        }
    }

    /**
     * Remove uploaded logo.
     */
    public function removeLogo(): void
    {
        $this->authorizeAbility('manageSettings');

        $this->logo = null;
        $this->existing_logo_path = null;

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if ($operator && $operator->logo_path) {
            $this->media()->delete($operator->logo_path);
            $operator->update(['logo_path' => null]);
            $this->dispatch('setup-progress-updated');
        }

        $this->highlightLogo = true;
    }

    /**
     * Validate logo upon upload. Highlight stays until Brand settings are saved.
     */
    public function updatedLogo(): void
    {
        $this->validate([
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:10240'],
        ]);
    }

    /**
     * Tidy what operators usually paste: links without https://, lower-case tag IDs,
     * and the whole Search Console meta tag instead of just its code.
     */
    private function normalizeLinksAndTrackingIds(): void
    {
        foreach (['instagram_url', 'facebook_url', 'tiktok_url', 'youtube_url'] as $field) {
            $url = trim($this->{$field});
            if ($url !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
                $url = 'https://'.ltrim($url, '/');
            }
            $this->{$field} = $url;
        }

        $this->google_analytics_id = strtoupper(trim($this->google_analytics_id));
        $this->google_tag_manager_id = strtoupper(trim($this->google_tag_manager_id));
        $this->meta_pixel_id = trim($this->meta_pixel_id);

        $verification = trim($this->google_site_verification);
        if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $verification, $match) === 1) {
            $verification = $match[1];
        }
        $this->google_site_verification = $verification;
    }

    /**
     * Update agent brand identity, logo, WhatsApp schedule, social links, and notifications.
     */
    public function updateBrandSettings(): void
    {
        $this->authorizeAbility('manageSettings');

        $this->normalizeLinksAndTrackingIds();

        $user = Auth::user();

        $validated = $this->validate([
            'agency_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'contact_whatsapp' => ['nullable', 'string', 'max:30'],
            'whatsapp_prefilled_message' => ['nullable', 'string', 'max:255'],
            'whatsapp_schedule_mode' => ['required', 'string', 'in:schedule,always'],
            'whatsapp_timezone' => ['required', 'string', 'max:100'],
            'whatsapp_start_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'whatsapp_end_time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'whatsapp_days' => ['array'],
            'brand_color' => ['nullable', 'string', 'regex:/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/'],
            'reservation_code_prefix' => ['required', 'string', 'max:8', 'regex:/^[A-Za-z0-9]+$/'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:10240'],
            'instagram_url' => ['nullable', 'url:https', 'max:255'],
            'facebook_url' => ['nullable', 'url:https', 'max:255'],
            'tiktok_url' => ['nullable', 'url:https', 'max:255'],
            'youtube_url' => ['nullable', 'url:https', 'max:255'],
            'google_analytics_id' => ['nullable', 'string', 'regex:'.Operator::TRACKING_ID_PATTERNS['google_analytics_id']],
            'meta_pixel_id' => ['nullable', 'string', 'regex:'.Operator::TRACKING_ID_PATTERNS['meta_pixel_id']],
            'google_tag_manager_id' => ['nullable', 'string', 'regex:'.Operator::TRACKING_ID_PATTERNS['google_tag_manager_id']],
            'google_site_verification' => ['nullable', 'string', 'regex:'.Operator::TRACKING_ID_PATTERNS['google_site_verification']],
            'booking_notification_email' => ['required', 'email', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if ($operator) {
            $customDomains = app(CustomDomainService::class);

            if (trim($this->custom_domain) !== '') {
                $this->custom_domain = $customDomains->connect($operator, $this->custom_domain)->domain;
            } else {
                $customDomains->disconnect($operator);
            }

            $logoPath = $this->existing_logo_path;
            if ($this->logo) {
                if ($operator->logo_path) {
                    $this->media()->delete($operator->logo_path);
                }
                $logoPath = $this->media()->storeUpload($this->logo, $this->operatorMediaDirectory('brand'), MediaStore::LOGO_MAX_WIDTH);
                $this->existing_logo_path = $logoPath;
                $this->logo = null;
            }

            $settings = $operator->settings ?? [];
            $settings['brand_color'] = $validated['brand_color'] ?? '#4f46e5';
            $settings['reservation_code_prefix'] = \App\Models\Reservation::normalizeCodePrefix($validated['reservation_code_prefix'] ?? 'RSV');
            $settings['whatsapp_prefilled_message'] = $validated['whatsapp_prefilled_message'] ?? '';
            $this->reservation_code_prefix = $settings['reservation_code_prefix'];
            $settings['whatsapp_schedule'] = [
                'mode' => $validated['whatsapp_schedule_mode'],
                'timezone' => $validated['whatsapp_timezone'],
                'start_time' => $validated['whatsapp_start_time'],
                'end_time' => $validated['whatsapp_end_time'],
                'days' => $this->whatsapp_days,
            ];
            $settings['social_links'] = [
                'instagram' => $validated['instagram_url'] ?? null,
                'facebook' => $validated['facebook_url'] ?? null,
                'tiktok' => $validated['tiktok_url'] ?? null,
                'youtube' => $validated['youtube_url'] ?? null,
            ];
            // Analytics pixels are a paid feature; keep any stored values untouched
            // rather than trusting inputs the plan should not be able to submit.
            if ($operator->hasFeature('tracking_pixels')) {
                $settings['tracking'] = [
                    'google_analytics_id' => $validated['google_analytics_id'] ?? null,
                    'meta_pixel_id' => $validated['meta_pixel_id'] ?? null,
                    'google_tag_manager_id' => $validated['google_tag_manager_id'] ?? null,
                    'google_site_verification' => $validated['google_site_verification'] ?? null,
                ];
            }
            $operator->update([
                'name' => $validated['agency_name'],
                'bio' => $validated['bio'] ?? null,
                'contact_whatsapp' => $validated['contact_whatsapp'] ?? null,
                'logo_path' => $logoPath,
                'booking_notification_email' => $validated['booking_notification_email'],
                'billing_email' => $validated['billing_email'],
                'settings' => $settings,
            ]);

            $this->syncSetupHighlights($operator->fresh());
        }

        $this->saved = true;
        $this->dispatch('brand-updated');
        $this->dispatch('setup-progress-updated');
        $this->dispatch('toast', message: __('Brand settings saved.'), type: 'success');
    }

    /**
     * DNS records the operator must add for their own website address (empty when none is connected).
     *
     * @return list<array{type: string, name: string, value: string, purpose: string}>
     */
    public function customDomainRecords(): array
    {
        $operator = $this->currentOperator;

        return $operator ? (app(CustomDomainService::class)->currentFor($operator)?->requiredDnsRecords() ?? []) : [];
    }

    /**
     * Check whether the operator's own website address already points here and has its padlock.
     */
    public function verifyCustomDomainDns(CustomDomainService $customDomains): void
    {
        $this->authorizeAbility('manageSettings');

        $operator = $this->currentOperator;
        $domain = $operator ? $customDomains->currentFor($operator) : null;

        if (! $domain) {
            $this->dispatch(
                'toast',
                message: __('Type your website address and save first.'),
                type: 'error',
            );

            return;
        }

        $domain = $customDomains->check($domain);

        [$message, $type] = match (true) {
            $domain->isLive() => [__('The address :domain is live with its padlock.', ['domain' => $domain->domain]), 'success'],
            $domain->status === \App\Enums\DomainStatus::Active => [__('The address :domain is connected. The padlock appears by itself in a few minutes.', ['domain' => $domain->domain]), 'success'],
            $domain->status === \App\Enums\DomainStatus::Verifying => [__('We can see :domain. The padlock is being set up, which can take a few minutes.', ['domain' => $domain->domain]), 'success'],
            $domain->status === \App\Enums\DomainStatus::Failed => [__('The records for :domain do not match. Check them against the list and try again.', ['domain' => $domain->domain]), 'error'],
            default => [__('We cannot see :domain pointing here yet. Add the records shown, then try again in 15 minutes. Changes at your domain shop can take a little while.', ['domain' => $domain->domain]), 'error'],
        };

        $this->dispatch('toast', message: $message, type: $type);
    }
}; ?>

<div class="space-y-6 w-full">
    <div class="space-y-6">
        <!-- Unified Settings Navigation -->
        <x-settings-nav />

        <x-page-header
            :title="__('Brand & Identity')"
            :subtitle="__('Customize your public storefront branding, logo, instant WhatsApp operating hours, and social media presence.')"
            icon="fa-paintbrush"
        />

        @if ($highlightLogo || $highlightBio || $highlightBookingNotificationEmail || $highlightBillingEmail)
            <div
                class="flex items-start gap-3 rounded-[10px] border border-[#FFEF4D]/50 bg-[#FFEF4D]/15 px-4 py-3 dark:border-[#FFEF4D]/25 dark:bg-[#FFEF4D]/10"
                role="status"
            >
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] bg-[#FFEF4D] text-[#12181E]" aria-hidden="true">
                    <i class="fa-solid fa-list-check text-sm"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-[#12181E] dark:text-white">{{ __('Finish the highlighted fields') }}</p>
                    <p class="mt-0.5 text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                        {{ __('Yellow fields are still needed before guests can pay you.') }}
                    </p>
                </div>
            </div>
        @endif

        <!-- Main Settings Form -->
        <form
            wire:submit="updateBrandSettings"
            class="w-full space-y-6"
            x-data
            x-init="
                $nextTick(() => {
                    const hash = window.location.hash;
                    const target = hash
                        ? document.querySelector(hash)
                        : document.querySelector('[data-setup-needed]');
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                })
            "
        >
            <!-- Card 1: Brand Logo & Visual Assets -->
            <x-setup-needed :needed="$highlightLogo" anchor="setup-logo">
                <div class="space-y-4 rounded-[12px] border border-[#E4E5E9] bg-white p-5 sm:p-6 shadow-none dark:border-[#1E2433] dark:bg-[#10141d]">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-[8px] bg-[#F4F5F7] dark:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] text-xs">
                        <i class="fa-solid fa-image"></i>
                    </span>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7]">
                        {{ __('Brand Logo & Visual Identity') }}
                    </h3>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 sm:gap-6 pt-1">
                    <!-- Logo Preview -->
                    <div class="relative group w-full sm:w-24">
                        <div
                            class="w-full h-36 sm:w-24 sm:h-24 rounded-[8px] border-2 border-dashed border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#141821] flex items-center justify-center overflow-hidden shadow-none">
                            @if ($logo)
                                <img src="{{ $logo->temporaryUrl() }}" alt="Logo preview"
                                    class="w-full h-full object-cover" />
                            @elseif ($existing_logo_path)
                                <img src="{{ $this->mediaUrl($existing_logo_path) }}" alt="Logo"
                                    class="w-full h-full object-cover" />
                            @else
                                <div class="text-center p-2 text-[#5A6578] dark:text-[#9DA4B2]">
                                    <i class="fa-solid fa-cloud-arrow-up text-2xl mb-1 block"></i>
                                    <span class="text-[10px] font-semibold uppercase">{{ __('No Logo') }}</span>
                                </div>
                            @endif
                        </div>

                        @if ($logo || $existing_logo_path)
                            <button type="button" wire:click="removeLogo"
                                class="absolute -top-2 -right-2 h-7 w-7 rounded-full bg-rose-500 hover:bg-rose-600 text-white flex items-center justify-center text-xs shadow-none transition cursor-pointer"
                                title="{{ __('Remove Logo') }}">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        @endif
                    </div>

                    <!-- Upload Input & Guidance -->
                    <div class="space-y-2 flex-1">
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                            <label
                                class="h-11 sm:h-9 px-4 w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-[6px] bg-[#F4F5F7] dark:bg-[#1E2433] hover:bg-[#E4E5E9] dark:hover:bg-[#283042] text-[#12181E] dark:text-[#E4E5E9] text-xs font-medium transition border border-[#E4E5E9] dark:border-[#1E2433] cursor-pointer">
                                <i class="fa-solid fa-upload text-[#5A6578] dark:text-[#FFEF4D]"></i>
                                <span>{{ __('Upload New Logo') }}</span>
                                <input type="file" wire:model="logo"
                                    accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" />
                            </label>

                            <div wire:loading wire:target="logo"
                                class="text-xs font-medium text-[#12181E] dark:text-[#FFEF4D] inline-flex items-center gap-1.5">
                                <i class="fa-solid fa-spinner fa-spin"></i>
                                {{ __('Uploading...') }}
                            </div>
                        </div>
                        <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __('Supported formats: PNG (with transparency), JPG, WEBP, or SVG. Up to 10MB.') }}
                        </p>
                        <x-input-error :messages="$errors->get('logo')" />
                    </div>
                </div>
                </div>
            </x-setup-needed>

            <!-- Card 2: Brand Profile Details -->
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-5">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-[8px] bg-[#F4F5F7] dark:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] text-xs">
                        <i class="fa-solid fa-id-card"></i>
                    </span>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7]">
                        {{ __('Business Information & Colors') }}
                    </h3>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-label for="agency_name" :value="__('Business / Brand Name')" required />
                            <x-input id="agency_name" wire:model="agency_name" type="text"
                                placeholder="e.g. Bali Snorkel & Treks" :error="$errors->has('agency_name')" />
                            <x-input-error :messages="$errors->get('agency_name')" />
                        </div>

                        <div>
                            <x-label for="brand_color" :value="__('Brand Accent Color (Hex)')" />
                            <div class="space-y-2 mt-1">
                                @php
                                    $previewHex = preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $brand_color)
                                        ? $brand_color
                                        : '#4f46e5';
                                @endphp
                                <!-- Unified Hex Input & Interactive Color Picker Bubble -->
                                <div class="relative flex items-center">
                                    <div
                                        class="absolute left-2.5 flex items-center justify-center pointer-events-none z-10">
                                        <span
                                            class="w-5 h-5 rounded-full border border-black/10 dark:border-white/20 shrink-0 transition-transform duration-200"
                                            style="background-color: {{ $previewHex }};"></span>
                                    </div>
                                    <input id="brand_color_picker" type="color" wire:model.live="brand_color"
                                        class="absolute left-2.5 w-5 h-5 opacity-0 cursor-pointer z-20" />
                                    <x-input id="brand_color" wire:model.live.debounce.250ms="brand_color"
                                        type="text" placeholder="#4f46e5" class="pl-11 font-mono text-xs uppercase"
                                        :error="$errors->has('brand_color')" />
                                </div>

                                <!-- Preset Curated Palette Swatches (Sleek Circular Dots) -->
                                <div class="flex items-center gap-2 pt-0.5 overflow-x-auto">
                                    <span
                                        class="text-[10px] uppercase font-semibold tracking-wider text-[#5A6578] dark:text-[#9DA4B2] shrink-0">{{ __('Presets:') }}</span>
                                    <div class="flex items-center gap-2 py-0.5 px-0.5">
                                        @foreach ([['label' => 'Indigo', 'hex' => '#4f46e5'], ['label' => 'Ocean Sky', 'hex' => '#0284c7'], ['label' => 'Emerald Marine', 'hex' => '#059669'], ['label' => 'Coral Sunset', 'hex' => '#ea580c'], ['label' => 'Royal Purple', 'hex' => '#7c3aed'], ['label' => 'Rose Pink', 'hex' => '#e11d48'], ['label' => 'Amber Gold', 'hex' => '#d97706'], ['label' => 'Slate Navy', 'hex' => '#334155']] as $palette)
                                            @php
                                                $isSelected = strtolower($brand_color) === strtolower($palette['hex']);
                                            @endphp
                                            <button type="button"
                                                 wire:click="$set('brand_color', '{{ $palette['hex'] }}')"
                                                 class="w-6 h-6 rounded-full transition-all duration-150 cursor-pointer shrink-0 relative flex items-center justify-center shadow-none hover:scale-110 active:scale-95 {{ $isSelected ? 'ring-2 ring-offset-2 ring-[#12181E] dark:ring-white ring-offset-white dark:ring-offset-[#10141d] scale-105 z-10' : 'hover:ring-1 hover:ring-offset-1 hover:ring-[#E4E5E9] dark:hover:ring-[#1E2433]' }}"
                                                 style="background-color: {{ $palette['hex'] }};"
                                                 title="{{ $palette['label'] }} ({{ $palette['hex'] }})">
                                                 @if ($isSelected)
                                                     <i
                                                         class="fa-solid fa-check text-[9px] text-white"></i>
                                                 @endif
                                             </button>
                                         @endforeach
                                     </div>
                                 </div>
                             </div>
                             <x-input-error :messages="$errors->get('brand_color')" />
                         </div>
                     </div>

                     <div class="max-w-sm">
                         <x-label for="reservation_code_prefix" :value="__('Booking code prefix')" />
                         <x-input id="reservation_code_prefix" wire:model="reservation_code_prefix" type="text"
                             maxlength="8" class="font-mono uppercase" placeholder="RSV"
                             :error="$errors->has('reservation_code_prefix')" />
                         <p class="mt-1.5 text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">
                             {{ __('Guest booking codes look like :example. Letters and numbers only. Default is RSV.', [
                                 'example' => strtoupper($reservation_code_prefix !== '' ? $reservation_code_prefix : 'RSV').'-A1B2C3D4',
                             ]) }}
                         </p>
                         <x-input-error :messages="$errors->get('reservation_code_prefix')" />
                     </div>

                     <!-- Live Color Theme Preview Box -->
                     @php
                         $previewHex = preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $brand_color)
                             ? $brand_color
                             : '#4f46e5';
                     @endphp
                     <div
                         class="p-4 rounded-[8px] bg-[#F9FAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] space-y-3">
                         <div class="flex items-center justify-between">
                             <span
                                 class="text-xs font-semibold text-[#12181E] dark:text-[#F4F5F7] flex items-center gap-1.5">
                                 <i class="fa-solid fa-wand-magic-sparkles text-xs"
                                     style="color: {{ $previewHex }}"></i>
                                 {{ __('Live Storefront Accent Preview') }}
                             </span>
                             <span
                                 class="font-mono text-[10px] font-semibold px-2 py-0.5 rounded-[4px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] text-[#12181E] dark:text-[#E4E5E9]">
                                 {{ $previewHex }}
                             </span>
                         </div>

                         <div class="flex flex-wrap items-center gap-3 pt-1">
                             <!-- Preview Button -->
                             <button type="button" style="background-color: {{ $previewHex }}; color: #ffffff;"
                                 class="h-9 px-4 rounded-[6px] font-semibold text-xs shadow-none transition inline-flex items-center gap-1.5 cursor-default">
                                 <i class="fa-solid fa-bolt text-[11px]"></i>
                                 <span>{{ __('Book Now Button') }}</span>
                             </button>

                             <!-- Preview Tag / Badge -->
                             <span
                                 style="background-color: {{ $previewHex }}1a; color: {{ $previewHex }}; border-color: {{ $previewHex }}33;"
                                 class="px-2.5 py-0.5 rounded-[4px] text-[11px] font-semibold uppercase tracking-wider border">
                                 {{ __('Featured Package') }}
                             </span>

                             <!-- Preview Text Link -->
                             <span style="color: {{ $previewHex }};"
                                 class="text-xs font-semibold cursor-default hover:underline">
                                 {{ __('Text Link & Pricing Highlight') }} &rarr;
                             </span>
                         </div>
                     </div>

                     <x-setup-needed :needed="$highlightBio" anchor="setup-bio" @class(['space-y-1', 'p-3 sm:p-3.5' => $highlightBio])>
                         <x-label for="bio" :value="__('Storefront Introduction / Bio')" required />
                         <x-textarea id="bio" wire:model.live.debounce.300ms="bio" rows="3"
                             placeholder="Tell guests about your experience, services, and local expertise..."
                             :error="$errors->has('bio')" />
                         <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                             {{ __('Displayed prominently on your public storefront header.') }}</p>
                         <x-input-error :messages="$errors->get('bio')" />
                     </x-setup-needed>
                 </div>
             </div>

             <!-- Card 3: WhatsApp Storefront Integration & Online Hours Schedule -->
             <div
                 class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-5">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-[8px] bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 text-xs">
                        <i class="fa-brands fa-whatsapp"></i>
                    </span>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7]">
                        {{ __('WhatsApp Instant Guest Chat & Online Hours') }}
                    </h3>
                </div>

                <!-- Basic WhatsApp Details -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-label for="contact_whatsapp" :value="__('WhatsApp Contact Number')" required />
                        <x-input id="contact_whatsapp" wire:model="contact_whatsapp" type="text"
                            placeholder="+62 812 3456 7890" :error="$errors->has('contact_whatsapp')" />
                        <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                            {{ __('Floating chat button will be active on your storefront.') }}</p>
                        <x-input-error :messages="$errors->get('contact_whatsapp')" />
                    </div>

                    <div>
                        <x-label for="whatsapp_prefilled_message" :value="__('Pre-filled Guest Greeting Message')" />
                        <x-input id="whatsapp_prefilled_message" wire:model="whatsapp_prefilled_message"
                            type="text" placeholder="Hi, I would like to inquire about your packages."
                            :error="$errors->has('whatsapp_prefilled_message')" />
                        <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                            {{ __('Default greeting pre-filled when a guest taps the chat button.') }}</p>
                        <x-input-error :messages="$errors->get('whatsapp_prefilled_message')" />
                    </div>
                </div>

                <!-- Operating Hours & Online Settings Schedule Box -->
                <div
                    class="p-4 sm:p-5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] space-y-4">
                    <div
                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                        <div>
                            <h4
                                class="text-xs sm:text-sm font-semibold text-[#12181E] dark:text-white flex items-center gap-2">
                                <i class="fa-solid fa-clock text-[#FFEF4D]"></i>
                                {{ __('Online Support Schedule & Status Indicators') }}
                            </h4>
                            <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                                {{ __('Controls the live Online / Away indicator dot and response expectation badge on your storefront.') }}
                            </p>
                        </div>

                        <!-- Schedule Mode Toggle -->
                        <div
                            class="inline-flex rounded-[8px] bg-[#F4F5F7] dark:bg-[#141821] p-1 shrink-0 border border-[#E4E5E9] dark:border-[#1E2433]">
                            <button type="button" wire:click="$set('whatsapp_schedule_mode', 'schedule')"
                                class="px-3 py-1 text-xs font-medium rounded-[6px] transition {{ $whatsapp_schedule_mode === 'schedule' ? 'bg-white dark:bg-[#1E2433] text-[#12181E] dark:text-white shadow-none' : 'text-[#5A6578] dark:text-[#9DA4B2] hover:text-[#12181E] dark:hover:text-white' }}">
                                {{ __('Custom Hours') }}
                            </button>
                            <button type="button" wire:click="$set('whatsapp_schedule_mode', 'always')"
                                class="px-3 py-1 text-xs font-medium rounded-[6px] transition {{ $whatsapp_schedule_mode === 'always' ? 'bg-white dark:bg-[#1E2433] text-[#12181E] dark:text-white shadow-none' : 'text-[#5A6578] dark:text-[#9DA4B2] hover:text-[#12181E] dark:hover:text-white' }}">
                                {{ __('24/7 Always Online') }}
                            </button>
                        </div>
                    </div>

                    @if ($whatsapp_schedule_mode === 'schedule')
                        <div class="space-y-4 animate-fade-in">
                            <!-- Timezone & Daily Hours -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <!-- Timezone Selector (Defaults to UTC+8 WITA) -->
                                <div>
                                    <x-label for="whatsapp_timezone" :value="__('Operating Timezone')" required />
                                    <x-select id="whatsapp_timezone" wire:model="whatsapp_timezone" :searchable="true"
                                        :options="[
                                            'Asia/Makassar' => 'WITA (UTC+8 - Bali, Lombok, Makassar) [Default]',
                                            'Asia/Jakarta' => 'WIB (UTC+7 - Jakarta, Surabaya, Sumatra)',
                                            'Asia/Jayapura' => 'WIT (UTC+9 - Papua, Maluku)',
                                            'Asia/Singapore' => 'SGT (UTC+8 - Singapore, Malaysia)',
                                            'Asia/Bangkok' => 'ICT (UTC+7 - Bangkok, Indochina)',
                                            'Asia/Tokyo' => 'JST (UTC+9 - Tokyo)',
                                            'Australia/Perth' => 'AWST (UTC+8 - Western Australia)',
                                            'UTC' => 'UTC (Universal Coordinated Time)',
                                        ]" :error="$errors->has('whatsapp_timezone')" />
                                    <x-input-error :messages="$errors->get('whatsapp_timezone')" />
                                </div>

                                <!-- Start Time -->
                                <div>
                                    <x-label for="whatsapp_start_time" :value="__('Opening Time')" required />
                                    <x-input id="whatsapp_start_time" wire:model="whatsapp_start_time" type="time"
                                        :error="$errors->has('whatsapp_start_time')" />
                                    <x-input-error :messages="$errors->get('whatsapp_start_time')" />
                                </div>

                                <!-- End Time -->
                                <div>
                                    <x-label for="whatsapp_end_time" :value="__('Closing Time')" required />
                                    <x-input id="whatsapp_end_time" wire:model="whatsapp_end_time" type="time"
                                        :error="$errors->has('whatsapp_end_time')" />
                                    <x-input-error :messages="$errors->get('whatsapp_end_time')" />
                                </div>
                            </div>

                            <!-- Active Days Selector -->
                            <div class="space-y-2">
                                <x-label :value="__('Active Operating Days (e.g. Mon - Fri)')" />
                                <div class="flex flex-wrap gap-2">
                                    @php
                                        $dayOptions = [
                                            'mon' => __('Mon'),
                                            'tue' => __('Tue'),
                                            'wed' => __('Wed'),
                                            'thu' => __('Thu'),
                                            'fri' => __('Fri'),
                                            'sat' => __('Sat'),
                                            'sun' => __('Sun'),
                                        ];
                                    @endphp

                                    @foreach ($dayOptions as $key => $label)
                                        @php
                                            $isActive = in_array($key, $whatsapp_days, true);
                                        @endphp
                                        <button type="button" wire:click="toggleDay('{{ $key }}')"
                                            class="h-9 px-3.5 rounded-[6px] text-xs font-semibold border transition-all cursor-pointer select-none {{ $isActive ? 'bg-[#FFEF4D] border-[#FFEF4D] text-[#12181E] shadow-none' : 'bg-white dark:bg-[#10141d] border-[#E4E5E9] dark:border-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2] hover:border-[#12181E]/30' }}">
                                            {{ $label }}
                                            @if ($isActive)
                                                <i class="fa-solid fa-check ml-1 text-[10px]"></i>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('whatsapp_days')" />
                            </div>
                        </div>
                    @else
                        <div
                            class="p-3 rounded-[8px] bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-xs font-medium flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-sm"></i>
                            <span>{{ __('Your storefront WhatsApp chat widget will display an active "Online" green pulse indicator 24 hours a day, 7 days a week.') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Card: Notification Channels -->
            <div
                class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-5">
                <div class="flex items-center gap-2.5 pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-[8px] bg-[#F4F5F7] dark:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] text-xs">
                        <i class="fa-solid fa-bell"></i>
                    </span>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7]">
                        {{ __('Notification Channels & Email Routing') }}
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-setup-needed
                        :needed="$highlightBookingNotificationEmail"
                        anchor="setup-booking-notification-email"
                        :hint="__('Needed for bookings — save to confirm')"
                        @class(['space-y-1', 'p-3 sm:p-3.5' => $highlightBookingNotificationEmail])
                    >
                        <x-label for="booking_notification_email" :value="__('Guest Booking Notifications Email')" required />
                        <x-input id="booking_notification_email" wire:model="booking_notification_email"
                            type="email" placeholder="bookings@yourdomain.com" :error="$errors->has('booking_notification_email')" />
                        <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                            {{ __('Receives instant alerts for new guest bookings, cancellations, and schedule updates.') }}
                        </p>
                        <x-input-error :messages="$errors->get('booking_notification_email')" />
                    </x-setup-needed>

                    <x-setup-needed
                        :needed="$highlightBillingEmail"
                        anchor="setup-billing-email"
                        :hint="__('Needed for bookings — save to confirm')"
                        @class(['space-y-1', 'p-3 sm:p-3.5' => $highlightBillingEmail])
                    >
                        <x-label for="billing_email" :value="__('Platform & Billing Statements Email')" required />
                        <x-input id="billing_email" wire:model="billing_email" type="email"
                            placeholder="finance@yourdomain.com" :error="$errors->has('billing_email')" />
                        <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                            {{ __('Receives payout settlement receipts, platform invoices, and critical account security notices.') }}
                        </p>
                        <x-input-error :messages="$errors->get('billing_email')" />
                    </x-setup-needed>
                </div>
            </div>

            <!-- Advanced Configuration Accordion (Progressive Disclosure) -->
            <div x-data="{ showAdvanced: false }"
                class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] overflow-hidden shadow-none">
                <button type="button" @click="showAdvanced = !showAdvanced"
                    class="w-full p-4 sm:p-5 flex items-center justify-between hover:bg-[#F9FAFB] dark:hover:bg-[#10141d] transition cursor-pointer text-left select-none">
                    <div class="flex items-center gap-3">
                        <div
                            class="size-8 rounded-[6px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-xs font-semibold shrink-0">
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-xs sm:text-sm font-semibold text-[#12181E] dark:text-white">
                                    {{ __('Advanced Configurations & Marketing') }}
                                </h4>
                                <span
                                    class="px-2 py-0.5 rounded-[4px] text-[10px] font-medium uppercase bg-[#F4F5F7] dark:bg-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2] border border-[#E4E5E9] dark:border-[#1E2433]">
                                    {{ __('Optional') }}
                                </span>
                            </div>
                            <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                                {{ __('Social media, your own website address, and ads tracking.') }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="size-7 rounded-[6px] bg-[#F4F5F7] dark:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#5A6578] dark:text-[#9DA4B2] flex items-center justify-center text-xs">
                        <i class="fa-solid transition-transform duration-200"
                            :class="showAdvanced ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </div>
                </button>

                <div x-show="showAdvanced" x-collapse
                    class="space-y-5 p-4 sm:p-6 pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                    <!-- Card: Social Media Links -->
                    <div
                        class="p-4 sm:p-5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] space-y-4">
                        <div class="flex items-center gap-2.5 pb-2.5 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-[6px] bg-white dark:bg-[#10141d] text-[#12181E] dark:text-[#E4E5E9] border border-[#E4E5E9] dark:border-[#1E2433] text-xs">
                                <i class="fa-solid fa-share-nodes"></i>
                            </span>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7]">
                                {{ __('Social Media Links') }}
                            </h3>
                        </div>

                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __('Add Instagram, Facebook, TikTok, and YouTube so guests can find you.') }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="instagram_url" :value="__('Instagram Profile / URL')" />
                                <div class="relative">
                                    <i
                                        class="fa-brands fa-instagram absolute left-3.5 top-1/2 -translate-y-1/2 text-pink-500 text-xs"></i>
                                    <x-input id="instagram_url" wire:model="instagram_url" type="text"
                                        placeholder="https://instagram.com/yourhandle" class="pl-9"
                                        :error="$errors->has('instagram_url')" />
                                </div>
                                <x-input-error :messages="$errors->get('instagram_url')" />
                            </div>

                            <div>
                                <x-label for="facebook_url" :value="__('Facebook Page URL')" />
                                <div class="relative">
                                    <i
                                        class="fa-brands fa-facebook absolute left-3.5 top-1/2 -translate-y-1/2 text-blue-600 text-xs"></i>
                                    <x-input id="facebook_url" wire:model="facebook_url" type="text"
                                        placeholder="https://facebook.com/yourpage" class="pl-9"
                                        :error="$errors->has('facebook_url')" />
                                </div>
                                <x-input-error :messages="$errors->get('facebook_url')" />
                            </div>

                            <div>
                                <x-label for="tiktok_url" :value="__('TikTok Profile URL')" />
                                <div class="relative">
                                    <i
                                        class="fa-brands fa-tiktok absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-900 dark:text-white text-xs"></i>
                                    <x-input id="tiktok_url" wire:model="tiktok_url" type="text"
                                        placeholder="https://tiktok.com/@yourhandle" class="pl-9"
                                        :error="$errors->has('tiktok_url')" />
                                </div>
                                <x-input-error :messages="$errors->get('tiktok_url')" />
                            </div>

                            <div class="sm:col-span-2">
                                <x-label for="youtube_url" :value="__('YouTube Channel URL')" />
                                <div class="relative">
                                    <i
                                        class="fa-brands fa-youtube absolute left-3.5 top-1/2 -translate-y-1/2 text-red-600 text-xs"></i>
                                    <x-input id="youtube_url" wire:model="youtube_url" type="text"
                                        placeholder="https://youtube.com/@yourchannel" class="pl-9"
                                        :error="$errors->has('youtube_url')" />
                                </div>
                                <x-input-error :messages="$errors->get('youtube_url')" />
                            </div>
                        </div>
                    </div>

                    <!-- Card: Custom Website Domain (Agency and above) -->
                    @php
                        $hasCustomDomain = $this->currentOperator?->hasFeature('custom_domain') ?? false;
                    @endphp
                    <div
                        class="p-4 sm:p-5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] space-y-4">
                        <div
                            class="flex items-center justify-between pb-2.5 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-[6px] bg-white dark:bg-[#10141d] text-[#12181E] dark:text-[#E4E5E9] border border-[#E4E5E9] dark:border-[#1E2433] text-xs">
                                    <i class="fa-solid fa-globe"></i>
                                </span>
                                <h3
                                    class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7]">
                                    {{ __('Custom Website Domain (`yourbrand.com`)') }}
                                </h3>
                            </div>
                            @if ($hasCustomDomain)
                                <span
                                    class="px-2 py-0.5 rounded-[4px] text-[10px] font-semibold uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ __('Active & Unlocked') }}
                                </span>
                            @else
                                <span
                                    class="px-2 py-0.5 rounded-[4px] text-[10px] font-semibold uppercase bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700 flex items-center gap-1">
                                    <i class="fa-solid fa-lock text-[9px]"></i>
                                    <span>{{ __('Agency') }}</span>
                                </span>
                            @endif
                        </div>

                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">
                            {{ __('This storefront is your website — guests book and pay here. Use yourname.com if you do not already have a site (typical for freelance guides). Use tours.yourname.com if you already have a website and only want bookings on a smaller name. After the name points here, the padlock appears by itself in a few minutes.') }}
                        </p>

                        @if (!$hasCustomDomain)
                            <div
                                class="p-3.5 sm:p-4 rounded-[8px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="flex size-8 shrink-0 items-center justify-center rounded-[6px] bg-[#F4F5F7] dark:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] text-xs">
                                        <i class="fa-solid fa-crown text-[#8a7808] dark:text-[#FFEF4D]"></i>
                                    </span>
                                    <div>
                                        <p class="text-xs font-semibold text-[#12181E] dark:text-white">
                                            {{ __('Custom Domains Require the Agency Plan') }}</p>
                                        <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">
                                            {{ __('Upgrade to Agency to use your own website address.') }}
                                        </p>
                                    </div>
                                </div>
                                <a href="{{ route('settings.plan') }}"
                                    class="h-8 px-3 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-semibold text-xs transition inline-flex items-center gap-1.5 shrink-0 self-start sm:self-auto shadow-none"
                                    wire:navigate>
                                    <i class="fa-solid fa-crown text-[10px]"></i>
                                    <span>{{ __('Upgrade Plan') }}</span>
                                </a>
                            </div>
                        @endif

                        <div class="{{ !$hasCustomDomain ? 'opacity-50 pointer-events-none' : '' }} space-y-4">
                            <div>
                                <x-label for="custom_domain" :value="__('Your website address')" />
                                <div class="relative">
                                    <i
                                        class="fa-solid fa-link absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                    <x-input id="custom_domain" wire:model="custom_domain" type="text"
                                        placeholder="yourname.com" class="pl-9 font-mono text-xs" :disabled="!$hasCustomDomain"
                                        :error="$errors->has('custom_domain')" />
                                </div>
                                <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                                    {{ __('Type yourname.com if this is your only website, or tours.yourname.com if you already have a site.') }}
                                </p>
                                <x-input-error :messages="$errors->get('custom_domain')" />
                            </div>

                            <!-- DNS records to add (from CustomDomainService / the provider) -->
                            @php
                                $customDomainModel = $this->currentOperator
                                    ? app(\App\Services\CustomDomainService::class)->currentFor($this->currentOperator)
                                    : null;
                                $dnsRecords = $this->customDomainRecords();
                            @endphp

                            <div
                                class="p-4 sm:p-5 rounded-[8px] bg-white dark:bg-[#10141d] text-[#12181E] dark:text-[#F4F5F7] space-y-4 border border-[#E4E5E9] dark:border-[#1E2433] shadow-none">
                                <div
                                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between border-b border-[#E4E5E9] dark:border-[#1E2433] pb-3">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="flex size-7 shrink-0 items-center justify-center rounded-[6px] bg-[#F4F5F7] dark:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] text-xs">
                                            <i class="fa-solid fa-network-wired"></i>
                                        </span>
                                        <div>
                                            <h4
                                                class="font-semibold text-xs text-[#12181E] dark:text-white uppercase tracking-wider">
                                                {{ __('How to connect your own address') }}</h4>
                                            <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">
                                                {{ __('Save your address first. Then add every setting below where you bought the name.') }}
                                            </p>
                                        </div>
                                    </div>

                                    @if ($customDomainModel)
                                        @if ($customDomainModel->isLive())
                                            <span
                                                class="px-2 py-0.5 rounded-[4px] text-[10px] font-semibold uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-500/40 flex items-center gap-1">
                                                <i class="fa-solid fa-circle-check text-[9px]"></i>
                                                <span>{{ __('Connected & padlock on') }}</span>
                                            </span>
                                        @elseif ($customDomainModel->status === \App\Enums\DomainStatus::Failed)
                                            <span
                                                class="px-2 py-0.5 rounded-[4px] text-[10px] font-semibold uppercase tracking-wider bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300 border border-rose-300 dark:border-rose-500/40 flex items-center gap-1">
                                                <i class="fa-solid fa-triangle-exclamation text-[9px]"></i>
                                                <span>{{ __('Settings do not match') }}</span>
                                            </span>
                                        @else
                                            <span
                                                class="px-2 py-0.5 rounded-[4px] text-[10px] font-semibold uppercase tracking-wider bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300 border border-amber-300 dark:border-amber-500/40 flex items-center gap-1">
                                                <i class="fa-solid fa-clock text-[9px] animate-pulse"></i>
                                                <span>{{ __('Still waiting') }}</span>
                                            </span>
                                        @endif
                                    @endif
                                </div>

                                @if ($dnsRecords === [])
                                    <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] italic">
                                        {{ __('Save your website address and the settings to add will show here.') }}
                                    </p>
                                @else
                                    <!-- DNS Record Spec Mobile Cards -->
                                    <div class="space-y-3 md:hidden">
                                        @foreach ($dnsRecords as $record)
                                            <div
                                                class="rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#10141d] p-3 space-y-2">
                                                <div
                                                    class="flex items-center justify-between gap-2 text-[10px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">
                                                    <span>{{ $record['type'] }}</span>
                                                    <span
                                                        class="font-mono text-[#12181E] dark:text-amber-300 break-all">{{ $record['name'] }}</span>
                                                </div>
                                                <p
                                                    class="font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-400 select-all break-all">
                                                    {{ $record['value'] }}</p>
                                                <button type="button" x-data="{ copied: false }"
                                                    x-on:click="navigator.clipboard.writeText(@js($record['value'])); copied = true; setTimeout(() => copied = false, 2000)"
                                                    class="h-9 w-full rounded-[6px] bg-white dark:bg-[#10141d] hover:bg-[#F4F5F7] dark:hover:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] font-sans font-medium text-xs transition border border-[#E4E5E9] dark:border-[#1E2433] shadow-none cursor-pointer inline-flex items-center justify-center gap-1.5">
                                                    <i class="fa-solid"
                                                        :class="copied ? 'fa-check text-emerald-600' : 'fa-copy text-slate-400'"></i>
                                                    <span
                                                        x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy Target') }}'"></span>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="hidden md:block overflow-x-auto">
                                        <table class="w-full text-left text-xs font-mono border-collapse">
                                            <thead>
                                                <tr
                                                    class="text-[10px] font-semibold uppercase text-[#5A6578] dark:text-[#9DA4B2] border-b border-[#E4E5E9] dark:border-[#1E2433] pb-2">
                                                    <th class="py-2 px-3">{{ __('Type of setting') }}</th>
                                                    <th class="py-2 px-3">{{ __('The name you own') }}</th>
                                                    <th class="py-2 px-3">{{ __('Point it at') }}</th>
                                                    <th class="py-2 px-3 text-right">{{ __('Action') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody
                                                class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433] font-medium text-[#12181E] dark:text-[#E4E5E9]">
                                                @foreach ($dnsRecords as $record)
                                                    <tr>
                                                        <td class="py-2.5 px-3">
                                                            <span
                                                                class="px-2 py-0.5 rounded-[4px] bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] font-semibold text-[11px] border border-[#FFEF4D]/30">{{ $record['type'] }}</span>
                                                        </td>
                                                        <td
                                                            class="py-2.5 px-3 font-mono font-semibold text-[#12181E] dark:text-amber-300 break-all">
                                                            {{ $record['name'] }}
                                                        </td>
                                                        <td
                                                            class="py-2.5 px-3 text-emerald-700 dark:text-emerald-400 font-semibold select-all break-all">
                                                            {{ $record['value'] }}
                                                        </td>
                                                        <td class="py-2.5 px-3 text-right" x-data="{ copied: false }">
                                                            <button type="button"
                                                                x-on:click="navigator.clipboard.writeText(@js($record['value'])); copied = true; setTimeout(() => copied = false, 2000)"
                                                                class="px-2.5 py-1 rounded-[6px] bg-[#F4F5F7] dark:bg-[#1E2433] hover:bg-[#E4E5E9] dark:hover:bg-[#283042] text-[#12181E] dark:text-[#E4E5E9] font-sans font-medium text-[10px] transition border border-[#E4E5E9] dark:border-[#1E2433] shadow-none cursor-pointer inline-flex items-center gap-1">
                                                                <i class="fa-solid"
                                                                    :class="copied ?
                                                                        'fa-check text-emerald-600 dark:text-emerald-400' :
                                                                        'fa-copy text-slate-400'"></i>
                                                                <span
                                                                    x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy Target') }}'"></span>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                <!-- Quick Instructions -->
                                <div
                                    class="space-y-2 text-[11px] text-[#5A6578] dark:text-[#9DA4B2] pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                                    <span
                                        class="font-semibold text-[#12181E] dark:text-white block uppercase tracking-wider text-[10px]">{{ __('What to ask your website host:') }}</span>
                                    <ol
                                        class="list-decimal list-inside space-y-1 text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed font-sans">
                                        <li>{{ __('Sign in where you bought the website name (GoDaddy, Niagahoster, Rumahweb, or similar).') }}
                                        </li>
                                        <li>{{ __('Open the page for website-name settings. It is often called DNS or Domain.') }}
                                        </li>
                                        <li>{{ __('Add each setting above exactly as shown: the type, the name, and where it points. An A setting on @ may be called ALIAS or ANAME at some shops.') }}
                                        </li>
                                        <li>{{ __('Save, then tap Check connection. The padlock appears by itself a few minutes after the name points here.') }}
                                        </li>
                                    </ol>
                                </div>

                                <!-- Verification Action Button -->
                                <div
                                    class="pt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-[#E4E5E9] dark:border-[#1E2433]">
                                    <span class="text-[10px] text-[#5A6578] dark:text-[#9DA4B2] font-sans">
                                        <i class="fa-solid fa-circle-info text-[#8a7808] dark:text-[#FFEF4D] mr-1"></i>
                                        {{ __('The change can take a few minutes. If it is not ready, try again in 15 minutes.') }}
                                    </span>
                                    <button type="button" wire:click="verifyCustomDomainDns"
                                        class="h-9 w-full sm:w-auto px-4 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-sans font-semibold text-xs shadow-none transition flex items-center justify-center gap-1.5 cursor-pointer shrink-0">
                                        <i class="fa-solid fa-rotate text-[10px]" wire:loading.class="animate-spin"
                                            wire:target="verifyCustomDomainDns"></i>
                                        <span>{{ __('Check connection') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Marketing Tracking Pixels -->
                    @php
                        $hasTracking = $this->currentOperator?->hasFeature('tracking_pixels') ?? false;
                    @endphp
                    <div
                        class="p-4 sm:p-5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] space-y-4">
                        <div
                            class="flex items-center justify-between pb-2.5 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-[6px] bg-white dark:bg-[#10141d] text-[#12181E] dark:text-[#E4E5E9] border border-[#E4E5E9] dark:border-[#1E2433] text-xs">
                                    <i class="fa-solid fa-chart-line"></i>
                                </span>
                                <h3
                                    class="text-xs font-semibold uppercase tracking-wider text-[#12181E] dark:text-[#F4F5F7]">
                                    {{ __('Marketing Tracking Pixels') }}
                                </h3>
                            </div>
                            @if ($hasTracking)
                                <span
                                    class="px-2 py-0.5 rounded-[4px] text-[10px] font-semibold uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ __('Active & Unlocked') }}
                                </span>
                            @else
                                <span
                                    class="px-2 py-0.5 rounded-[4px] text-[10px] font-semibold uppercase bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700 flex items-center gap-1">
                                    <i class="fa-solid fa-lock text-[9px]"></i>
                                    <span>{{ __('Growth') }}</span>
                                </span>
                            @endif
                        </div>

                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">
                            {{ __('Connect your marketing pixels to measure conversions on Facebook / Instagram Ads.') }}
                        </p>

                        @if (!$hasTracking)
                            <div
                                class="p-3.5 sm:p-4 rounded-[8px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="flex size-8 shrink-0 items-center justify-center rounded-[6px] bg-[#F4F5F7] dark:bg-[#1E2433] text-[#12181E] dark:text-[#E4E5E9] text-xs">
                                        <i class="fa-solid fa-crown text-[#8a7808] dark:text-[#FFEF4D]"></i>
                                    </span>
                                    <div>
                                        <p class="text-xs font-semibold text-[#12181E] dark:text-white">
                                            {{ __('Requires Growth or Agency') }}</p>
                                        <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">
                                            {{ __('Upgrade to unlock Google Analytics 4, Meta Pixel ROAS tracking, and automated 12-hour review request emails.') }}
                                        </p>
                                    </div>
                                </div>
                                <a href="{{ route('settings.plan') }}"
                                    class="h-8 px-3 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-semibold text-xs transition inline-flex items-center gap-1.5 shrink-0 self-start sm:self-auto shadow-none"
                                    wire:navigate>
                                    <i class="fa-solid fa-crown text-[10px]"></i>
                                    <span>{{ __('Upgrade Plan') }}</span>
                                </a>
                            </div>
                        @endif

                        <div
                            class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1 {{ !$hasTracking ? 'opacity-50 pointer-events-none' : '' }}">
                            <!-- Meta / Facebook Pixel -->
                            <div>
                                <x-label for="meta_pixel_id" :value="__('Meta / Facebook Pixel ID')" />
                                <div class="relative">
                                    <i
                                        class="fa-brands fa-meta absolute left-3.5 top-1/2 -translate-y-1/2 text-blue-600 text-xs"></i>
                                    <x-input id="meta_pixel_id" wire:model="meta_pixel_id" type="text"
                                        placeholder="e.g. 123456789012345" class="pl-9 font-mono text-xs"
                                        :disabled="!$hasTracking" :error="$errors->has('meta_pixel_id')" />
                                </div>
                                <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                                    {{ __('Tracks PageViews and Purchase events for Facebook & Instagram Ads.') }}</p>
                                <x-input-error :messages="$errors->get('meta_pixel_id')" />
                            </div>

                            <!-- Google Analytics 4 -->
                            <div>
                                <x-label for="google_analytics_id" :value="__('Google Analytics 4 Measurement ID')" />
                                <div class="relative">
                                    <i
                                        class="fa-brands fa-google absolute left-3.5 top-1/2 -translate-y-1/2 text-amber-500 text-xs"></i>
                                    <x-input id="google_analytics_id" wire:model="google_analytics_id" type="text"
                                        placeholder="e.g. G-XXXXXXXXXX" class="pl-9 font-mono text-xs"
                                        :disabled="!$hasTracking" :error="$errors->has('google_analytics_id')" />
                                </div>
                                <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                                    {{ __('Tracks visitor traffic and purchase conversions on your storefront.') }}
                                </p>
                                <x-input-error :messages="$errors->get('google_analytics_id')" />
                            </div>

                            <!-- Google Tag Manager -->
                            <div>
                                <x-label for="google_tag_manager_id" :value="__('Google Tag Manager (GTM) Container ID')" />
                                <div class="relative">
                                    <i
                                        class="fa-solid fa-tag absolute left-3.5 top-1/2 -translate-y-1/2 text-indigo-500 text-xs"></i>
                                    <x-input id="google_tag_manager_id" wire:model="google_tag_manager_id"
                                        type="text" placeholder="e.g. GTM-XXXXXXX" class="pl-9 font-mono text-xs"
                                        :disabled="!$hasTracking" :error="$errors->has('google_tag_manager_id')" />
                                </div>
                                <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                                    {{ __('Optional custom tag manager container.') }}</p>
                                <x-input-error :messages="$errors->get('google_tag_manager_id')" />
                            </div>

                            <!-- Google Search Console Site Verification -->
                            <div>
                                <x-label for="google_site_verification" :value="__('Google Search Console Verification Tag / Code')" />
                                <div class="relative">
                                    <i
                                        class="fa-solid fa-magnifying-glass-chart absolute left-3.5 top-1/2 -translate-y-1/2 text-emerald-500 text-xs"></i>
                                    <x-input id="google_site_verification" wire:model="google_site_verification"
                                        type="text" placeholder="e.g. google-site-verification=... or code"
                                        class="pl-9 font-mono text-xs" :disabled="!$hasTracking" :error="$errors->has('google_site_verification')" />
                                </div>
                                <p class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2] mt-1">
                                    {{ __('Injected into <head> for 1-click Google Search Console domain verification.') }}
                                </p>
                                <x-input-error :messages="$errors->get('google_site_verification')" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button & Success Toast -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center pt-2">
                <x-button variant="primary" type="submit" data-test="update-brand-button"
                    class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="updateBrandSettings">
                    <i class="fa-solid fa-floppy-disk mr-1.5 text-xs" wire:loading.remove wire:target="updateBrandSettings"></i>
                    <i class="fa-solid fa-spinner fa-spin mr-1.5 text-xs" wire:loading wire:target="updateBrandSettings"></i>
                    <span wire:loading.remove wire:target="updateBrandSettings">{{ __('Save Brand Settings') }}</span>
                    <span wire:loading wire:target="updateBrandSettings">{{ __('Saving…') }}</span>
                </x-button>

                <div x-data="{ shown: false, timeout: null }" x-init="@this.on('brand-updated', () => {
                    clearTimeout(timeout);
                    shown = true;
                    timeout = setTimeout(() => { shown = false }, 2500);
                })"
                    x-show.transition.out.opacity.duration.1500ms="shown" x-transition:leave.opacity.duration.1500ms
                    style="display: none;"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    <i class="fa-solid fa-circle-check"></i>
                    {{ __('Brand settings saved successfully.') }}
                </div>
            </div>
        </form>
    </div>

</div>
