<?php

use App\Models\Operator;
use App\Concerns\ResolvesCurrentOperator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Brand Settings')] class extends Component {
    use WithFileUploads;
    use ResolvesCurrentOperator;

    // Brand Logo
    public $logo;
    public ?string $existing_logo_path = null;

    // Business & Brand identity fields
    public string $agency_name = '';
    public string $bio = '';
    public string $brand_color = '#4f46e5';

    // WhatsApp Storefront Integration & Schedule
    public string $contact_whatsapp = '';
    public string $whatsapp_prefilled_message = '';
    public string $whatsapp_schedule_mode = 'schedule'; // schedule, always
    public string $whatsapp_timezone = 'Asia/Makassar'; // Default UTC+8
    public string $whatsapp_start_time = '08:00';
    public string $whatsapp_end_time = '18:00';
    /** @var array<int, string> */
    public array $whatsapp_days = ['mon', 'tue', 'wed', 'thu', 'fri'];

    // Social Media & Web Links
    public string $website_url = '';
    public string $instagram_url = '';
    public string $facebook_url = '';
    public string $tiktok_url = '';
    public string $youtube_url = '';

    // Custom Domain (Enterprise)
    public string $custom_domain = '';

    // Marketing, Tracking Pixels & Review Links
    public string $google_analytics_id = '';
    public string $meta_pixel_id = '';
    public string $google_tag_manager_id = '';
    public string $google_site_verification = '';
    public string $review_url = '';

    // Notification Channels
    public string $booking_notification_email = '';
    public string $billing_email = '';

    public bool $saved = false;


    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if ($operator) {
            $this->agency_name = $operator->name;
            $this->bio = $operator->bio ?? '';
            $this->contact_whatsapp = $operator->contact_whatsapp ?? '';
            $this->brand_color = $operator->brand_color ?? '#4f46e5';
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
            $this->website_url = (string) ($social['website'] ?? '');
            $this->instagram_url = (string) ($social['instagram'] ?? '');
            $this->facebook_url = (string) ($social['facebook'] ?? '');
            $this->tiktok_url = (string) ($social['tiktok'] ?? '');
            $this->youtube_url = (string) ($social['youtube'] ?? '');

            $tracking = $settings['tracking'] ?? [];
            $this->google_analytics_id = (string) ($tracking['google_analytics_id'] ?? '');
            $this->meta_pixel_id = (string) ($tracking['meta_pixel_id'] ?? '');
            $this->google_tag_manager_id = (string) ($tracking['google_tag_manager_id'] ?? '');
            $this->google_site_verification = (string) ($tracking['google_site_verification'] ?? '');

            $marketing = $settings['marketing'] ?? [];
            $this->review_url = (string) ($marketing['review_url'] ?? '');

            $customDomain = $operator->domains()->where('type', \App\Enums\DomainType::Custom)->first();
            $this->custom_domain = $customDomain ? (string) $customDomain->domain : '';
        } else {
            $this->booking_notification_email = $user->email;
            $this->billing_email = $user->email;
        }
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
        $this->logo = null;
        $this->existing_logo_path = null;

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if ($operator && $operator->logo_path) {
            Storage::disk('public')->delete($operator->logo_path);
            $operator->update(['logo_path' => null]);
        }
    }

    /**
     * Validate logo upon upload.
     */
    public function updatedLogo(): void
    {
        $this->validate([
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg,gif', 'max:10240'],
        ]);
    }

    /**
     * Update agent brand identity, logo, WhatsApp schedule, social links, and notifications.
     */
    public function updateBrandSettings(): void
    {
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
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg,gif', 'max:10240'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'string', 'max:255'],
            'tiktok_url' => ['nullable', 'string', 'max:255'],
            'youtube_url' => ['nullable', 'string', 'max:255'],
            'google_analytics_id' => ['nullable', 'string', 'max:50'],
            'meta_pixel_id' => ['nullable', 'string', 'max:50'],
            'google_tag_manager_id' => ['nullable', 'string', 'max:50'],
            'google_site_verification' => ['nullable', 'string', 'max:255'],
            'review_url' => ['nullable', 'url', 'max:500'],
            'booking_notification_email' => ['required', 'email', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Operator|null $operator */
        $operator = $this->currentOperator;
        if ($operator) {
            if ($this->custom_domain !== '') {
                if (! $operator->hasFeature('custom_domain')) {
                    $this->addError('custom_domain', __('Custom domain connection requires the Enterprise subscription plan.'));
                    return;
                }

                $cleanDomain = strtolower(trim((string) preg_replace('#^https?://#', '', rtrim($this->custom_domain, '/'))));
                $existing = \App\Models\OperatorDomain::where('domain', $cleanDomain)->where('operator_id', '!=', $operator->id)->exists();
                if ($existing) {
                    $this->addError('custom_domain', __('This domain is already registered to another operator.'));
                    return;
                }

                $operator->domains()->updateOrCreate(
                    ['type' => \App\Enums\DomainType::Custom],
                    ['domain' => $cleanDomain, 'status' => \App\Enums\DomainStatus::Pending]
                );
            } else {
                $operator->domains()->where('type', \App\Enums\DomainType::Custom)->delete();
            }

            $logoPath = $this->existing_logo_path;
            if ($this->logo) {
                if ($operator->logo_path) {
                    Storage::disk('public')->delete($operator->logo_path);
                }
                $logoPath = $this->logo->store('operators/logos', 'public');
                $this->existing_logo_path = $logoPath;
                $this->logo = null;
            }

            $settings = $operator->settings ?? [];
            $settings['brand_color'] = $validated['brand_color'] ?? '#4f46e5';
            $settings['whatsapp_prefilled_message'] = $validated['whatsapp_prefilled_message'] ?? '';
            $settings['whatsapp_schedule'] = [
                'mode' => $validated['whatsapp_schedule_mode'],
                'timezone' => $validated['whatsapp_timezone'],
                'start_time' => $validated['whatsapp_start_time'],
                'end_time' => $validated['whatsapp_end_time'],
                'days' => $this->whatsapp_days,
            ];
            $settings['social_links'] = [
                'website' => $validated['website_url'] ?? null,
                'instagram' => $validated['instagram_url'] ?? null,
                'facebook' => $validated['facebook_url'] ?? null,
                'tiktok' => $validated['tiktok_url'] ?? null,
                'youtube' => $validated['youtube_url'] ?? null,
            ];
            $settings['tracking'] = [
                'google_analytics_id' => $validated['google_analytics_id'] ?? null,
                'meta_pixel_id' => $validated['meta_pixel_id'] ?? null,
                'google_tag_manager_id' => $validated['google_tag_manager_id'] ?? null,
                'google_site_verification' => $validated['google_site_verification'] ?? null,
            ];
            $settings['marketing'] = [
                'review_url' => $validated['review_url'] ?? null,
            ];

            $operator->update([
                'name' => $validated['agency_name'],
                'bio' => $validated['bio'] ?? null,
                'contact_whatsapp' => $validated['contact_whatsapp'] ?? null,
                'logo_path' => $logoPath,
                'booking_notification_email' => $validated['booking_notification_email'],
                'billing_email' => $validated['billing_email'],
                'settings' => $settings,
            ]);
        }

        $this->saved = true;
        $this->dispatch('brand-updated');
    }

    /**
     * Test DNS CNAME record for custom domain.
     */
    public function verifyCustomDomainDns(): void
    {
        $operator = $this->currentOperator;
        if (! $operator || ! $this->custom_domain) {
            session()->flash('error', __('Please enter your custom domain name first.'));
            return;
        }

        $cleanDomain = strtolower(trim((string) preg_replace('#^https?://#', '', rtrim($this->custom_domain, '/'))));
        $targetHost = parse_url(config('app.url', 'https://emvi.id'), PHP_URL_HOST) ?? 'emvi.id';

        $records = @dns_get_record($cleanDomain, DNS_CNAME);
        $found = false;

        if ($records) {
            foreach ($records as $rec) {
                if (isset($rec['target']) && strtolower(rtrim((string) $rec['target'], '.')) === strtolower(rtrim($targetHost, '.'))) {
                    $found = true;
                    break;
                }
            }
        }

        $customDomainRecord = $operator->domains()->where('type', \App\Enums\DomainType::Custom)->first();

        if ($found) {
            if ($customDomainRecord) {
                $customDomainRecord->update([
                    'status' => \App\Enums\DomainStatus::Active,
                    'verified_at' => now(),
                    'ssl_issued_at' => now(),
                ]);
            }
            session()->flash('success', __('DNS verification successful! :domain is correctly pointing to :target.', ['domain' => $cleanDomain, 'target' => $targetHost]));
        } else {
            session()->flash('error', __('DNS CNAME record not detected yet for :domain pointing to :target. Please allow 5-15 minutes for global DNS propagation.', ['domain' => $cleanDomain, 'target' => $targetHost]));
        }
    }
}; ?>

<div class="space-y-6 max-w-6xl mx-auto">
    <!-- Desktop Notice on Mobile -->
    <x-desktop-only-notice
        :title="__('Brand Settings Best Managed on Desktop')"
        :description="__('Detailed logo uploads, operating schedule fine-tuning, and tracking pixels are optimized for desktop management.')"
    />

    <div class="hidden lg:block space-y-6">
        <!-- Unified Settings Navigation -->
        <x-settings-nav />

        <!-- Standalone Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400">
                    <i class="fa-solid fa-paintbrush text-lg"></i>
                </span>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __('Brand & Identity') }}
                </h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Customize your public storefront branding, logo, instant WhatsApp operating hours, and social media presence.') }}
            </p>
        </div>
    </div>

    <!-- Main Settings Form -->
    <form wire:submit="updateBrandSettings" class="w-full space-y-6">
        <!-- Card 1: Brand Logo & Visual Assets -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-xs">
                    <i class="fa-solid fa-image"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Brand Logo & Visual Identity') }}
                </h3>
            </div>

            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 pt-2">
                <!-- Logo Preview -->
                <div class="relative group">
                    <div class="w-24 h-24 rounded-2xl border-2 border-dashed border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800/60 flex items-center justify-center overflow-hidden shadow-xs">
                        @if ($logo)
                            <img src="{{ $logo->temporaryUrl() }}" alt="Logo preview" class="w-full h-full object-cover" />
                        @elseif ($existing_logo_path)
                            <img src="{{ Storage::url($existing_logo_path) }}" alt="Logo" class="w-full h-full object-cover" />
                        @else
                            <div class="text-center p-2 text-slate-400 dark:text-slate-500">
                                <i class="fa-solid fa-cloud-arrow-up text-2xl mb-1 block"></i>
                                <span class="text-[10px] font-bold uppercase">{{ __('No Logo') }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($logo || $existing_logo_path)
                        <button
                            type="button"
                            wire:click="removeLogo"
                            class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-rose-500 hover:bg-rose-600 text-white flex items-center justify-center text-xs shadow-md transition cursor-pointer"
                            title="{{ __('Remove Logo') }}"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    @endif
                </div>

                <!-- Upload Input & Guidance -->
                <div class="space-y-2 flex-1">
                    <div class="flex items-center gap-3">
                        <label class="h-10 px-4 inline-flex items-center gap-2 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-800 dark:text-slate-200 text-xs font-bold transition cursor-pointer">
                            <i class="fa-solid fa-upload text-indigo-500"></i>
                            <span>{{ __('Upload New Logo') }}</span>
                            <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif" class="hidden" />
                        </label>

                        <div wire:loading wire:target="logo" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            {{ __('Uploading...') }}
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        {{ __('Supported formats: PNG (with transparency), JPG, WEBP, or SVG. Up to 10MB.') }}
                    </p>
                    <x-input-error :messages="$errors->get('logo')" />
                </div>
            </div>
        </div>

        <!-- Card 2: Brand Profile Details -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/70 text-sky-600 dark:text-sky-400 text-xs">
                    <i class="fa-solid fa-id-card"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Business Information & Colors') }}
                </h3>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-label for="agency_name" :value="__('Business / Brand Name')" required />
                        <x-input id="agency_name" wire:model="agency_name" type="text" placeholder="e.g. Bali Snorkel & Treks" :error="$errors->has('agency_name')" />
                        <x-input-error :messages="$errors->get('agency_name')" />
                    </div>

                    <div>
                        <x-label for="brand_color" :value="__('Brand Accent Color (Hex)')" />
                        <div class="space-y-2.5 mt-1.5">
                            @php
                                $previewHex = preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $brand_color) ? $brand_color : '#4f46e5';
                            @endphp
                            <!-- Unified Hex Input & Interactive Color Picker Bubble -->
                            <div class="relative flex items-center">
                                <div class="absolute left-2.5 flex items-center justify-center pointer-events-none z-10">
                                    <span class="w-6 h-6 rounded-full shadow-inner border border-white/30 shrink-0 transition-transform duration-200" style="background-color: {{ $previewHex }};"></span>
                                </div>
                                <input id="brand_color_picker" type="color" wire:model.live="brand_color" class="absolute left-2.5 w-6 h-6 opacity-0 cursor-pointer z-20" />
                                <x-input id="brand_color" wire:model.live.debounce.250ms="brand_color" type="text" placeholder="#4f46e5" class="pl-11 font-mono text-xs uppercase" :error="$errors->has('brand_color')" />
                            </div>

                            <!-- Preset Curated Palette Swatches (Sleek Circular Dots) -->
                            <div class="flex items-center gap-2 pt-1 overflow-x-auto">
                                <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 shrink-0">{{ __('Presets:') }}</span>
                                <div class="flex items-center gap-2 py-1 px-1">
                                    @foreach ([
                                        ['label' => 'Indigo', 'hex' => '#4f46e5'],
                                        ['label' => 'Ocean Sky', 'hex' => '#0284c7'],
                                        ['label' => 'Emerald Marine', 'hex' => '#059669'],
                                        ['label' => 'Coral Sunset', 'hex' => '#ea580c'],
                                        ['label' => 'Royal Purple', 'hex' => '#7c3aed'],
                                        ['label' => 'Rose Pink', 'hex' => '#e11d48'],
                                        ['label' => 'Amber Gold', 'hex' => '#d97706'],
                                        ['label' => 'Slate Navy', 'hex' => '#334155'],
                                    ] as $palette)
                                        @php
                                            $isSelected = strtolower($brand_color) === strtolower($palette['hex']);
                                        @endphp
                                        <button
                                            type="button"
                                            wire:click="$set('brand_color', '{{ $palette['hex'] }}')"
                                            class="w-7 h-7 rounded-full transition-all duration-200 cursor-pointer shrink-0 relative flex items-center justify-center shadow-xs hover:scale-110 active:scale-95 {{ $isSelected ? 'ring-2 ring-offset-2 ring-indigo-600 dark:ring-white dark:ring-offset-zinc-900 scale-110 z-10' : 'hover:ring-2 hover:ring-offset-1 hover:ring-slate-300 dark:hover:ring-zinc-600' }}"
                                            style="background-color: {{ $palette['hex'] }};"
                                            title="{{ $palette['label'] }} ({{ $palette['hex'] }})"
                                        >
                                            @if ($isSelected)
                                                <i class="fa-solid fa-check text-[10px] text-white drop-shadow-xs"></i>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('brand_color')" />
                    </div>
                </div>

                <!-- Live Color Theme Preview Box -->
                @php
                    $previewHex = preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $brand_color) ? $brand_color : '#4f46e5';
                @endphp
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/40 border border-slate-200/80 dark:border-zinc-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <i class="fa-solid fa-wand-magic-sparkles text-xs" style="color: {{ $previewHex }}"></i>
                            {{ __('Live Storefront Accent Preview') }}
                        </span>
                        <span class="font-mono text-[10px] font-bold px-2 py-0.5 rounded-md bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700">
                            {{ $previewHex }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <!-- Preview Button -->
                        <button type="button" style="background-color: {{ $previewHex }}; color: #ffffff;" class="h-9 px-4 rounded-xl font-bold text-xs shadow-xs transition inline-flex items-center gap-1.5 cursor-default">
                            <i class="fa-solid fa-bolt text-[11px]"></i>
                            <span>{{ __('Book Now Button') }}</span>
                        </button>

                        <!-- Preview Tag / Badge -->
                        <span style="background-color: {{ $previewHex }}1a; color: {{ $previewHex }}; border-color: {{ $previewHex }}33;" class="px-3 py-1 rounded-lg text-xs font-black uppercase tracking-wider border">
                            {{ __('Featured Package') }}
                        </span>

                        <!-- Preview Text Link -->
                        <span style="color: {{ $previewHex }};" class="text-xs font-bold cursor-default hover:underline">
                            {{ __('Text Link & Pricing Highlight') }} &rarr;
                        </span>
                    </div>
                </div>

                <div>
                    <x-label for="bio" :value="__('Storefront Introduction / Bio')" required />
                    <x-textarea id="bio" wire:model="bio" rows="3" placeholder="Tell guests about your experience, services, and local expertise..." :error="$errors->has('bio')" />
                    <p class="text-[11px] text-slate-500 mt-1">{{ __('Displayed prominently on your public storefront header.') }}</p>
                    <x-input-error :messages="$errors->get('bio')" />
                </div>
            </div>
        </div>

        <!-- Card 3: WhatsApp Storefront Integration & Online Hours Schedule -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 text-xs">
                    <i class="fa-brands fa-whatsapp"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('WhatsApp Instant Guest Chat & Online Hours') }}
                </h3>
            </div>

            <!-- Basic WhatsApp Details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-label for="contact_whatsapp" :value="__('WhatsApp Contact Number')" required />
                    <x-input id="contact_whatsapp" wire:model="contact_whatsapp" type="text" placeholder="+62 812 3456 7890" :error="$errors->has('contact_whatsapp')" />
                    <p class="text-[11px] text-slate-500 mt-1">{{ __('Floating chat button will be active on your storefront.') }}</p>
                    <x-input-error :messages="$errors->get('contact_whatsapp')" />
                </div>

                <div>
                    <x-label for="whatsapp_prefilled_message" :value="__('Pre-filled Guest Greeting Message')" />
                    <x-input id="whatsapp_prefilled_message" wire:model="whatsapp_prefilled_message" type="text" placeholder="Hi, I would like to inquire about your packages." :error="$errors->has('whatsapp_prefilled_message')" />
                    <p class="text-[11px] text-slate-500 mt-1">{{ __('Default greeting pre-filled when a guest taps the chat button.') }}</p>
                    <x-input-error :messages="$errors->get('whatsapp_prefilled_message')" />
                </div>
            </div>

            <!-- Operating Hours & Online Settings Schedule Box -->
            <div class="p-5 rounded-2xl bg-slate-50/80 dark:bg-zinc-800/40 border border-slate-200/80 dark:border-zinc-800 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200 dark:border-zinc-700/60">
                    <div>
                        <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-clock text-indigo-500"></i>
                            {{ __('Online Support Schedule & Status Indicators') }}
                        </h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Controls the live Online / Away indicator dot and response expectation badge on your storefront.') }}
                        </p>
                    </div>

                    <!-- Schedule Mode Toggle -->
                    <div class="inline-flex rounded-xl bg-slate-200 dark:bg-zinc-700 p-1 shrink-0">
                        <button
                            type="button"
                            wire:click="$set('whatsapp_schedule_mode', 'schedule')"
                            class="px-3 py-1 text-xs font-bold rounded-lg transition {{ $whatsapp_schedule_mode === 'schedule' ? 'bg-white dark:bg-zinc-900 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-300' }}"
                        >
                            {{ __('Custom Hours') }}
                        </button>
                        <button
                            type="button"
                            wire:click="$set('whatsapp_schedule_mode', 'always')"
                            class="px-3 py-1 text-xs font-bold rounded-lg transition {{ $whatsapp_schedule_mode === 'always' ? 'bg-white dark:bg-zinc-900 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-300' }}"
                        >
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
                                <x-select
                                    id="whatsapp_timezone"
                                    wire:model="whatsapp_timezone"
                                    :searchable="true"
                                    :options="[
                                        'Asia/Makassar' => 'WITA (UTC+8 - Bali, Lombok, Makassar) [Default]',
                                        'Asia/Jakarta' => 'WIB (UTC+7 - Jakarta, Surabaya, Sumatra)',
                                        'Asia/Jayapura' => 'WIT (UTC+9 - Papua, Maluku)',
                                        'Asia/Singapore' => 'SGT (UTC+8 - Singapore, Malaysia)',
                                        'Asia/Bangkok' => 'ICT (UTC+7 - Bangkok, Indochina)',
                                        'Asia/Tokyo' => 'JST (UTC+9 - Tokyo)',
                                        'Australia/Perth' => 'AWST (UTC+8 - Western Australia)',
                                        'UTC' => 'UTC (Universal Coordinated Time)',
                                    ]"
                                    :error="$errors->has('whatsapp_timezone')"
                                />
                                <x-input-error :messages="$errors->get('whatsapp_timezone')" />
                            </div>

                            <!-- Start Time -->
                            <div>
                                <x-label for="whatsapp_start_time" :value="__('Opening Time')" required />
                                <x-input
                                    id="whatsapp_start_time"
                                    wire:model="whatsapp_start_time"
                                    type="time"
                                    :error="$errors->has('whatsapp_start_time')"
                                />
                                <x-input-error :messages="$errors->get('whatsapp_start_time')" />
                            </div>

                            <!-- End Time -->
                            <div>
                                <x-label for="whatsapp_end_time" :value="__('Closing Time')" required />
                                <x-input
                                    id="whatsapp_end_time"
                                    wire:model="whatsapp_end_time"
                                    type="time"
                                    :error="$errors->has('whatsapp_end_time')"
                                />
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
                                    <button
                                        type="button"
                                        wire:click="toggleDay('{{ $key }}')"
                                        class="h-9 px-3.5 rounded-xl text-xs font-bold border transition-all cursor-pointer select-none {{ $isActive ? 'bg-indigo-600 border-indigo-600 text-white shadow-xs' : 'bg-white dark:bg-zinc-800 border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-slate-300 hover:border-slate-300' }}"
                                    >
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
                    <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-sm"></i>
                        <span>{{ __('Your storefront WhatsApp chat widget will display an active "Online" green pulse indicator 24 hours a day, 7 days a week.') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Card: Notification Channels -->
        <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-xs">
                    <i class="fa-solid fa-bell"></i>
                </span>
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Notification Channels & Email Routing') }}
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-label for="booking_notification_email" :value="__('Guest Booking Notifications Email')" required />
                    <x-input id="booking_notification_email" wire:model="booking_notification_email" type="email" placeholder="bookings@yourdomain.com" :error="$errors->has('booking_notification_email')" />
                    <p class="text-[11px] text-slate-500 mt-1">{{ __('Receives instant alerts for new guest bookings, cancellations, and schedule updates.') }}</p>
                    <x-input-error :messages="$errors->get('booking_notification_email')" />
                </div>

                <div>
                    <x-label for="billing_email" :value="__('Platform & Billing Statements Email')" required />
                    <x-input id="billing_email" wire:model="billing_email" type="email" placeholder="finance@yourdomain.com" :error="$errors->has('billing_email')" />
                    <p class="text-[11px] text-slate-500 mt-1">{{ __('Receives payout settlement receipts, platform invoices, and critical account security notices.') }}</p>
                    <x-input-error :messages="$errors->get('billing_email')" />
                </div>
            </div>
        </div>

        <!-- Advanced Configuration Accordion (Progressive Disclosure) -->
        <div x-data="{ showAdvanced: false }" class="rounded-3xl bg-slate-50/80 dark:bg-zinc-800/40 border border-slate-200/80 dark:border-zinc-800 overflow-hidden">
            <button
                type="button"
                @click="showAdvanced = !showAdvanced"
                class="w-full p-5 flex items-center justify-between hover:bg-slate-100/70 dark:hover:bg-zinc-800/60 transition cursor-pointer text-left select-none"
            >
                <div class="flex items-center gap-3.5">
                    <div class="w-9 h-9 rounded-xl bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm shadow-2xs shrink-0">
                        <i class="fa-solid fa-sliders"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                                {{ __('Advanced Configurations & Marketing') }}
                            </h4>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-slate-200/80 dark:bg-zinc-700 text-slate-600 dark:text-slate-300">
                                {{ __('Optional') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Social media channels, custom website domain (CNAME), and Meta / Google Ads tracking.') }}
                        </p>
                    </div>
                </div>
                <div class="p-2 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-500 dark:text-slate-300 shadow-2xs">
                    <i class="fa-solid text-xs transition-transform duration-200" :class="showAdvanced ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </div>
            </button>

            <div x-show="showAdvanced" x-collapse class="space-y-6 p-4 sm:p-6 pt-2 border-t border-slate-200/60 dark:border-zinc-800">
                <!-- Card: Website & Social Media Links -->
                <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs space-y-4">
                    <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100 dark:border-zinc-800">
                        <span class="p-1.5 rounded-lg bg-pink-50 dark:bg-pink-950/70 text-pink-600 dark:text-pink-400 text-xs">
                            <i class="fa-solid fa-share-nodes"></i>
                        </span>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Website & Social Media Links') }}
                        </h3>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Connect your official online presence and social media profiles to boost trust with prospective guests.') }}
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-label for="website_url" :value="__('Official Website Link')" />
                            <div class="relative">
                                <i class="fa-solid fa-globe absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <x-input id="website_url" wire:model="website_url" type="url" placeholder="https://www.yourdomain.com" class="pl-9" :error="$errors->has('website_url')" />
                            </div>
                            <x-input-error :messages="$errors->get('website_url')" />
                        </div>

                        <div>
                            <x-label for="instagram_url" :value="__('Instagram Profile / URL')" />
                            <div class="relative">
                                <i class="fa-brands fa-instagram absolute left-3.5 top-1/2 -translate-y-1/2 text-pink-500 text-xs"></i>
                                <x-input id="instagram_url" wire:model="instagram_url" type="text" placeholder="https://instagram.com/yourhandle" class="pl-9" :error="$errors->has('instagram_url')" />
                            </div>
                            <x-input-error :messages="$errors->get('instagram_url')" />
                        </div>

                        <div>
                            <x-label for="facebook_url" :value="__('Facebook Page URL')" />
                            <div class="relative">
                                <i class="fa-brands fa-facebook absolute left-3.5 top-1/2 -translate-y-1/2 text-blue-600 text-xs"></i>
                                <x-input id="facebook_url" wire:model="facebook_url" type="text" placeholder="https://facebook.com/yourpage" class="pl-9" :error="$errors->has('facebook_url')" />
                            </div>
                            <x-input-error :messages="$errors->get('facebook_url')" />
                        </div>

                        <div>
                            <x-label for="tiktok_url" :value="__('TikTok Profile URL')" />
                            <div class="relative">
                                <i class="fa-brands fa-tiktok absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-900 dark:text-white text-xs"></i>
                                <x-input id="tiktok_url" wire:model="tiktok_url" type="text" placeholder="https://tiktok.com/@yourhandle" class="pl-9" :error="$errors->has('tiktok_url')" />
                            </div>
                            <x-input-error :messages="$errors->get('tiktok_url')" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-label for="youtube_url" :value="__('YouTube Channel URL')" />
                            <div class="relative">
                                <i class="fa-brands fa-youtube absolute left-3.5 top-1/2 -translate-y-1/2 text-red-600 text-xs"></i>
                                <x-input id="youtube_url" wire:model="youtube_url" type="text" placeholder="https://youtube.com/@yourchannel" class="pl-9" :error="$errors->has('youtube_url')" />
                            </div>
                            <x-input-error :messages="$errors->get('youtube_url')" />
                        </div>
                    </div>
                </div>

                <!-- Card: Custom Website Domain (Enterprise) -->
                @php
                    $hasCustomDomain = $this->currentOperator?->hasFeature('custom_domain') ?? false;
                @endphp
                <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2.5">
                            <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/70 text-sky-600 dark:text-sky-400 text-xs">
                                <i class="fa-solid fa-globe"></i>
                            </span>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('Custom Website Domain (`yourbrand.com`)') }}
                            </h3>
                        </div>
                        @if ($hasCustomDomain)
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                {{ __('Active & Unlocked') }}
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 flex items-center gap-1">
                                <i class="fa-solid fa-lock text-[9px]"></i>
                                <span>{{ __('Agency Ultimate') }}</span>
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Point your own custom domain (e.g. tours.baliadventures.com or youragency.com) to your storefront with automated SSL security.') }}
                    </p>

                    @if (! $hasCustomDomain)
                        <div class="p-4 rounded-2xl bg-gradient-to-r from-purple-500/10 via-indigo-500/10 to-transparent border border-purple-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="p-2 rounded-xl bg-purple-100 dark:bg-purple-950/80 text-purple-600 dark:text-purple-400 text-xs">
                                    <i class="fa-solid fa-crown"></i>
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-slate-900 dark:text-white">{{ __('Custom Domains Require Agency Ultimate Plan') }}</p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Upgrade to Agency Ultimate to white-label your storefront on your own .com domain.') }}</p>
                                </div>
                            </div>
                            <a href="{{ route('settings.plan') }}" class="px-3.5 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-xs transition inline-flex items-center gap-1.5 shrink-0 self-start sm:self-auto shadow-xs" wire:navigate>
                                <i class="fa-solid fa-crown text-[10px] text-amber-300"></i>
                                <span>{{ __('Upgrade Plan') }}</span>
                            </a>
                        </div>
                    @endif

                    <div class="{{ ! $hasCustomDomain ? 'opacity-50 pointer-events-none' : '' }} space-y-4">
                        <div>
                            <x-label for="custom_domain" :value="__('Your Custom Domain / Subdomain')" />
                            <div class="relative">
                                <i class="fa-solid fa-link absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <x-input id="custom_domain" wire:model="custom_domain" type="text" placeholder="tours.yourdomain.com" class="pl-9 font-mono text-xs" :disabled="! $hasCustomDomain" :error="$errors->has('custom_domain')" />
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1">{{ __('Enter the custom hostname where your guest booking storefront should be served (e.g. tours.yourdomain.com or booking.youragency.com).') }}</p>
                            <x-input-error :messages="$errors->get('custom_domain')" />
                        </div>

                        <!-- Step-by-Step DNS CNAME Configuration Box -->
                        @php
                            $targetHost = parse_url(config('app.url', 'https://emvi.id'), PHP_URL_HOST) ?? 'emvi.id';
                            $customDomainModel = $this->currentOperator?->domains()->where('type', \App\Enums\DomainType::Custom)->first();
                        @endphp

                        <div class="p-4 sm:p-5 rounded-2xl bg-slate-900 text-slate-100 space-y-4 shadow-md border border-slate-800">
                            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="p-1.5 rounded-lg bg-purple-500/20 text-purple-400 text-xs">
                                        <i class="fa-solid fa-network-wired"></i>
                                    </span>
                                    <div>
                                        <h4 class="font-extrabold text-xs text-white uppercase tracking-wider">{{ __('Step-by-Step DNS CNAME Setup') }}</h4>
                                        <p class="text-[11px] text-slate-400">{{ __('Point your custom domain DNS records to EMVI servers') }}</p>
                                    </div>
                                </div>

                                @if ($customDomainModel)
                                    @if ($customDomainModel->status === \App\Enums\DomainStatus::Active)
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 flex items-center gap-1">
                                            <i class="fa-solid fa-circle-check text-[9px]"></i>
                                            <span>{{ __('Verified & SSL Active') }}</span>
                                        </span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/40 flex items-center gap-1">
                                            <i class="fa-solid fa-clock text-[9px] animate-pulse"></i>
                                            <span>{{ __('Pending Propagation') }}</span>
                                        </span>
                                    @endif
                                @endif
                            </div>

                            <!-- DNS Record Spec Table -->
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs font-mono border-collapse">
                                    <thead>
                                        <tr class="text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-800 pb-2">
                                            <th class="py-2 px-3">{{ __('Record Type') }}</th>
                                            <th class="py-2 px-3">{{ __('Host / Name') }}</th>
                                            <th class="py-2 px-3">{{ __('Target / Points To') }}</th>
                                            <th class="py-2 px-3 text-right">{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/60 font-semibold text-slate-200">
                                        <tr>
                                            <td class="py-2.5 px-3">
                                                <span class="px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 font-bold text-[11px] border border-purple-500/30">CNAME</span>
                                            </td>
                                            <td class="py-2.5 px-3 text-amber-300">
                                                @if ($this->custom_domain)
                                                    @php
                                                        $parts = explode('.', strtolower(trim((string) preg_replace('#^https?://#', '', rtrim($this->custom_domain, '/')))));
                                                        $subdomain = count($parts) > 2 ? $parts[0] : '@';
                                                    @endphp
                                                    {{ $subdomain }}
                                                @else
                                                    <span class="text-slate-500 italic">{{ __('tours (or @)') }}</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3 text-emerald-400 font-bold select-all">
                                                {{ $targetHost }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right" x-data="{ copied: false }">
                                                <button
                                                    type="button"
                                                    x-on:click="navigator.clipboard.writeText('{{ $targetHost }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-sans font-bold text-[10px] transition border border-slate-700 cursor-pointer inline-flex items-center gap-1"
                                                >
                                                    <i class="fa-solid" :class="copied ? 'fa-check text-emerald-400' : 'fa-copy text-slate-400'"></i>
                                                    <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy Target') }}'"></span>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Quick 4-Step Instructions -->
                            <div class="space-y-2 text-[11px] text-slate-300 pt-2 border-t border-slate-800">
                                <span class="font-bold text-slate-200 block uppercase tracking-wider text-[10px]">{{ __('Quick Setup Steps:') }}</span>
                                <ol class="list-decimal list-inside space-y-1 text-slate-400 leading-relaxed font-sans">
                                    <li>{{ __('Log into your domain registrar account (Cloudflare, GoDaddy, Namecheap, Niagahoster, or Rumahweb).') }}</li>
                                    <li>{{ __('Go to your domain\'s DNS Management or Zone Editor panel.') }}</li>
                                    <li>{{ __('Add a new CNAME record with Host set to your subdomain and Target set to ') }} <strong class="text-emerald-400 font-mono">{{ $targetHost }}</strong>.</li>
                                    <li>{{ __('Save changes and click "Verify DNS Connection" below to issue your automated SSL certificate.') }}</li>
                                </ol>
                            </div>

                            <!-- Verification Action Button -->
                            <div class="pt-2 flex items-center justify-between gap-3 border-t border-slate-800">
                                <span class="text-[10px] text-slate-400 font-sans">
                                    <i class="fa-solid fa-circle-info text-purple-400 mr-1"></i>
                                    {{ __('DNS changes typically take 2 to 15 minutes to propagate globally.') }}
                                </span>
                                <button
                                    type="button"
                                    wire:click="verifyCustomDomainDns"
                                    class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-sans font-bold text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer shrink-0"
                                >
                                    <i class="fa-solid fa-rotate text-[10px]" wire:loading.class="animate-spin" wire:target="verifyCustomDomainDns"></i>
                                    <span>{{ __('Verify DNS Connection') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Marketing, Tracking Pixels & Review Links -->
                @php
                    $hasTracking = $this->currentOperator?->hasFeature('tracking_pixels') ?? false;
                @endphp
                <div class="p-5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2.5">
                            <span class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400 text-xs">
                                <i class="fa-solid fa-chart-line"></i>
                            </span>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                {{ __('Marketing, Tracking Pixels & Review Links') }}
                            </h3>
                        </div>
                        @if ($hasTracking)
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                {{ __('Active & Unlocked') }}
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 flex items-center gap-1">
                                <i class="fa-solid fa-lock text-[9px]"></i>
                                <span>{{ __('Pro Operator') }}</span>
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        {{ __('Connect your marketing pixels to measure conversions on Facebook / Instagram Ads and automatically invite guests to review your business after their trip.') }}
                    </p>

                    @if (! $hasTracking)
                        <div class="p-4 rounded-2xl bg-gradient-to-r from-purple-500/10 via-indigo-500/10 to-transparent border border-purple-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="p-2 rounded-xl bg-purple-100 dark:bg-purple-950/80 text-purple-600 dark:text-purple-400 text-xs">
                                    <i class="fa-solid fa-crown"></i>
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-slate-900 dark:text-white">{{ __('Requires Pro Operator or Agency Ultimate Tier') }}</p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Upgrade to unlock Google Analytics 4, Meta Pixel ROAS tracking, and automated 12-hour review request emails.') }}</p>
                                </div>
                            </div>
                            <a href="{{ route('settings.plan') }}" class="px-3.5 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-xs transition inline-flex items-center gap-1.5 shrink-0 self-start sm:self-auto shadow-xs" wire:navigate>
                                <i class="fa-solid fa-crown text-[10px] text-amber-300"></i>
                                <span>{{ __('Upgrade Plan') }}</span>
                            </a>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1 {{ ! $hasTracking ? 'opacity-50 pointer-events-none' : '' }}">
                        <!-- Meta / Facebook Pixel -->
                        <div>
                            <x-label for="meta_pixel_id" :value="__('Meta / Facebook Pixel ID')" />
                            <div class="relative">
                                <i class="fa-brands fa-meta absolute left-3.5 top-1/2 -translate-y-1/2 text-blue-600 text-xs"></i>
                                <x-input id="meta_pixel_id" wire:model="meta_pixel_id" type="text" placeholder="e.g. 123456789012345" class="pl-9 font-mono text-xs" :disabled="! $hasTracking" :error="$errors->has('meta_pixel_id')" />
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1">{{ __('Tracks PageViews and Purchase events for Facebook & Instagram Ads.') }}</p>
                            <x-input-error :messages="$errors->get('meta_pixel_id')" />
                        </div>

                        <!-- Google Analytics 4 -->
                        <div>
                            <x-label for="google_analytics_id" :value="__('Google Analytics 4 Measurement ID')" />
                            <div class="relative">
                                <i class="fa-brands fa-google absolute left-3.5 top-1/2 -translate-y-1/2 text-amber-500 text-xs"></i>
                                <x-input id="google_analytics_id" wire:model="google_analytics_id" type="text" placeholder="e.g. G-XXXXXXXXXX" class="pl-9 font-mono text-xs" :disabled="! $hasTracking" :error="$errors->has('google_analytics_id')" />
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1">{{ __('Tracks visitor traffic and purchase conversions on your storefront.') }}</p>
                            <x-input-error :messages="$errors->get('google_analytics_id')" />
                        </div>

                        <!-- Google Tag Manager -->
                        <div>
                            <x-label for="google_tag_manager_id" :value="__('Google Tag Manager (GTM) Container ID')" />
                            <div class="relative">
                                <i class="fa-solid fa-tag absolute left-3.5 top-1/2 -translate-y-1/2 text-indigo-500 text-xs"></i>
                                <x-input id="google_tag_manager_id" wire:model="google_tag_manager_id" type="text" placeholder="e.g. GTM-XXXXXXX" class="pl-9 font-mono text-xs" :disabled="! $hasTracking" :error="$errors->has('google_tag_manager_id')" />
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1">{{ __('Optional custom tag manager container.') }}</p>
                            <x-input-error :messages="$errors->get('google_tag_manager_id')" />
                        </div>

                        <!-- Google Search Console Site Verification -->
                        <div>
                            <x-label for="google_site_verification" :value="__('Google Search Console Verification Tag / Code')" />
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass-chart absolute left-3.5 top-1/2 -translate-y-1/2 text-emerald-500 text-xs"></i>
                                <x-input id="google_site_verification" wire:model="google_site_verification" type="text" placeholder="e.g. google-site-verification=... or code" class="pl-9 font-mono text-xs" :disabled="! $hasTracking" :error="$errors->has('google_site_verification')" />
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1">{{ __('Injected into <head> for 1-click Google Search Console domain verification.') }}</p>
                            <x-input-error :messages="$errors->get('google_site_verification')" />
                        </div>

                        <!-- Google Maps / TripAdvisor Review URL -->
                        <div>
                            <x-label for="review_url" :value="__('Google Maps or TripAdvisor Review URL')" />
                            <div class="relative">
                                <i class="fa-solid fa-star absolute left-3.5 top-1/2 -translate-y-1/2 text-amber-400 text-xs"></i>
                                <x-input id="review_url" wire:model="review_url" type="url" placeholder="https://g.page/r/your-business/review" class="pl-9" :disabled="! $hasTracking" :error="$errors->has('review_url')" />
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1">{{ __('Used in automated post-trip review invitation emails sent 12 hours after departure.') }}</p>
                            <x-input-error :messages="$errors->get('review_url')" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button & Success Toast -->
        <div class="flex items-center gap-4 pt-2">
            <x-button variant="primary" type="submit" data-test="update-brand-button" class="shadow-sm">
                <i class="fa-solid fa-floppy-disk mr-1 text-xs"></i>
                {{ __('Save Brand Settings') }}
            </x-button>

            <div x-data="{ shown: false, timeout: null }"
                 x-init="@this.on('brand-updated', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2500); })"
                 x-show.transition.out.opacity.duration.1500ms="shown"
                 x-transition:leave.opacity.duration.1500ms
                 style="display: none;"
                 class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                <i class="fa-solid fa-circle-check"></i>
                {{ __('Brand settings saved successfully.') }}
            </div>
        </div>
    </form>
    </div>
</div>
