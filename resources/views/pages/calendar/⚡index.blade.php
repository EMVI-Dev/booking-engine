<?php

use App\Models\Operator;
use App\Concerns\ResolvesCurrentOperator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Booking Calendar & Operations')] class extends Component {
    use ResolvesCurrentOperator;
    #[Url(as: 'view')]
    public string $viewMode = 'month'; // 'month' | 'timeline' | 'manifest' | 'heatmap'

    public function mount(): void
    {
        if (! in_array($this->viewMode, ['month', 'timeline', 'manifest', 'heatmap'])) {
            $this->viewMode = 'month';
        }
    }

    #[Computed]
    public function currentOperator(): ?Operator
    {
        return auth()->user()?->currentOperator();
    }

    #[Computed]
    public function hasTimelineFeature(): bool
    {
        return $this->currentOperator?->hasFeature('advanced_calendar') ?? false;
    }

    #[Computed]
    public function hasManifestFeature(): bool
    {
        return $this->currentOperator?->hasFeature('daily_manifest_export') ?? false;
    }

    #[Computed]
    public function hasHeatmapFeature(): bool
    {
        return $this->currentOperator?->hasFeature('capacity_heatmap') ?? false;
    }

    #[Computed]
    public function hasGoogleCalendarFeature(): bool
    {
        return $this->currentOperator?->hasFeature('google_calendar') ?? false;
    }

    public function switchView(string $mode): void
    {
        if (in_array($mode, ['month', 'timeline', 'manifest', 'heatmap'])) {
            $this->viewMode = $mode;
        }
    }

    public function openGoogleSyncModal(): void
    {
        if ($this->hasGoogleCalendarFeature) {
            $this->dispatch('open-modal', 'google-calendar-sync');
        } else {
            $this->redirect(route('settings.plan'), navigate: true);
        }
    }
};
?>

<div class="animate-fade-in space-y-6 print:space-y-0">
    <x-page-header
        class="print:hidden"
        :title="__('Booking Calendar & Availability')"
        :subtitle="__('Manage scheduled guest departures, resource allocation, run-sheet manifests, and blackout dates.')"
        icon="fa-calendar-days"
    >
        <x-slot:actions>
            <x-button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'google-calendar-sync')">
                <i class="fa-brands fa-google text-sm"></i>
                <span>{{ __('Sync iCal Feed') }}</span>
                @if (! $this->hasGoogleCalendarFeature)
                    <x-plan-badge title="{{ __('Requires Growth Plan') }}" />
                @endif
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="border-b border-op-line pb-3 print:hidden">
        <x-filter-tabs padded>
            <x-filter-tab :active="$viewMode === 'month'" icon="fa-calendar-days" wire:click="switchView('month')">
                {{ __('Month Grid') }}
            </x-filter-tab>
            <x-filter-tab :active="$viewMode === 'timeline'" icon="fa-bars-staggered" wire:click="switchView('timeline')">
                {{ __('Resource Timeline') }}
                @if (! $this->hasTimelineFeature)
                    <x-plan-badge title="{{ __('Requires Growth Plan') }}" />
                @endif
            </x-filter-tab>
            <x-filter-tab :active="$viewMode === 'manifest'" icon="fa-clipboard-list" wire:click="switchView('manifest')">
                {{ __('Daily Manifest') }}
                @if (! $this->hasManifestFeature)
                    <x-plan-badge title="{{ __('Requires Growth Plan') }}" />
                @endif
            </x-filter-tab>
            <x-filter-tab :active="$viewMode === 'heatmap'" icon="fa-fire-flame-curved" wire:click="switchView('heatmap')">
                {{ __('Capacity Heatmap') }}
                @if (! $this->hasHeatmapFeature)
                    <x-plan-badge :label="__('Agency')" title="{{ __('Requires Agency Plan') }}" />
                @endif
            </x-filter-tab>
        </x-filter-tabs>
    </div>

    <!-- Active Independent Subcomponent / Feature Gating -->
    <div>
        @if ($viewMode === 'month')
            <livewire:calendar.month-grid />
        @elseif ($viewMode === 'timeline')
            @if ($this->hasTimelineFeature)
                <livewire:calendar.resource-timeline />
            @else
                <x-feature-gate
                    :title="__('Resource Timeline & Capacity Matrix')"
                    :description="__('Visualize tour guide, vehicle, and activity capacity in a Gantt-style matrix across 7-day windows. Track seat occupancy progress bars and prevent overbookings.')"
                    requiredPlan="Growth"
                    planSlug="growth"
                    icon="fa-solid fa-bars-staggered"
                    :features="[
                        __('Visual Gantt timeline for activities, guides, and bookable resources'),
                        __('Live seat occupancy progress bars and percentage fill rates'),
                        __('1-Click multi-week navigation and resource scheduling'),
                        __('Instant identification of available capacity vs. booked slots'),
                    ]"
                />
            @endif
        @elseif ($viewMode === 'manifest')
            @if ($this->hasManifestFeature)
                <livewire:calendar.daily-manifest />
            @else
                <x-feature-gate
                    :title="__('Daily Passenger Run-Sheet & Manifest Export')"
                    :description="__('Generate printable daily passenger run-sheets for tour drivers, captains, and guides with lead guest names, pickup notes, and WhatsApp links.')"
                    requiredPlan="Growth"
                    planSlug="growth"
                    icon="fa-solid fa-clipboard-list"
                    :features="[
                        __('Printable departure manifests formatted for clipboards and PDF exports'),
                        __('Lead guest details, pax counts, and pickup location notes'),
                        __('1-Click direct WhatsApp launch for instant guest confirmation'),
                        __('Payment and confirmation status badges for ground crews'),
                    ]"
                />
            @endif
        @elseif ($viewMode === 'heatmap')
            @if ($this->hasHeatmapFeature)
                <livewire:calendar.capacity-heatmap />
            @else
                <x-feature-gate
                    :title="__('Capacity & Occupancy Heatmap Analytics')"
                    :description="__('Discover booking density and peak departure days with color-graded monthly utilization heatmaps. Analyze capacity load and optimize seasonal scheduling.')"
                    requiredPlan="Agency"
                    planSlug="agency"
                    icon="fa-solid fa-fire-flame-curved"
                    :features="[
                        __('Color-coded monthly heatmap showing occupancy density (0-100%)'),
                        __('Peak departure day identification and active booking trends'),
                        __('Total monthly passenger counts and average capacity load'),
                        __('Strategic insights for tour pricing adjustments and blackout planning'),
                    ]"
                />
            @endif
        @endif
    </div>

    <!-- Google / Apple / Outlook Calendar Live Sync Modal -->
    <x-modal name="google-calendar-sync" maxWidth="lg">
        @php
            $feedToken = $this->currentOperator?->getCalendarFeedToken() ?? '';
            $feedUrl = $feedToken ? route('calendar.feed', ['token' => $feedToken]) : '';
            $webcalUrl = $feedUrl ? preg_replace('/^https?:\/\//i', 'webcal://', $feedUrl) : '';
            $googleSubUrl = $feedUrl ? 'https://calendar.google.com/calendar/r?cid=' . urlencode($webcalUrl) : '';
            $feedHost = parse_url($feedUrl, PHP_URL_HOST);
            $isLocalHost = in_array($feedHost, ['localhost', '127.0.0.1'], true)
                || str_ends_with((string) $feedHost, '.test')
                || str_ends_with((string) $feedHost, '.emvi')
                || str_ends_with((string) $feedHost, '.local');
        @endphp
        <div class="p-4 sm:p-6 space-y-5" x-data="{ copied: false }">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-[8px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-calendar-check"></i>
                </span>
                <div>
                    <h3 class="text-[16px] sm:text-[18px] font-medium text-[#12181E] dark:text-white">
                        {{ __('Calendar Live Sync & iCal Feed') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Automatically stream reservations to Google Calendar, Apple Calendar, and Outlook in real-time.') }}
                    </p>
                </div>
            </div>

            @if (! $this->hasGoogleCalendarFeature)
                <!-- Feature Gating Notice inside modal -->
                <div class="p-4 sm:p-5 rounded-[12px] bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 space-y-3 text-center shadow-none">
                    <div class="w-10 h-10 rounded-[8px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-base mx-auto">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <div>
                        <h4 class="font-medium text-sm text-[#12181E] dark:text-white">{{ __('Growth Tier Feature') }}</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            {{ __('Live calendar subscriptions, automated manifest run-sheets, and WhatsApp dispatch are unlocked on Growth and Agency plans.') }}
                        </p>
                    </div>
                    <div class="pt-1">
                        <x-button :href="route('settings.plan')" variant="primary" class="w-full justify-center rounded-[6px] font-medium shadow-none">
                            <span>{{ __('Upgrade to Growth Plan') }}</span>
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </x-button>
                    </div>
                </div>
            @else
                <!-- 1-Click Subscription Quick Actions -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <a
                        href="{{ $googleSubUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="p-3 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#151a26] hover:bg-[#F3F4F6] dark:hover:bg-[#1E2433] transition text-left flex flex-col justify-between group shadow-none"
                    >
                        <i class="fa-brands fa-google text-rose-500 text-lg"></i>
                        <div class="mt-2">
                            <span class="font-medium text-xs text-[#12181E] dark:text-white block group-hover:text-black dark:group-hover:text-white">{{ __('Google') }}</span>
                            <span class="text-[10px] text-slate-400 block">{{ __('1-Click Add') }}</span>
                        </div>
                    </a>

                    <a
                        href="{{ $webcalUrl }}"
                        class="p-3 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#151a26] hover:bg-[#F3F4F6] dark:hover:bg-[#1E2433] transition text-left flex flex-col justify-between group shadow-none"
                    >
                        <i class="fa-brands fa-apple text-[#12181E] dark:text-white text-lg"></i>
                        <div class="mt-2">
                            <span class="font-medium text-xs text-[#12181E] dark:text-white block group-hover:text-black dark:group-hover:text-white">{{ __('Apple / Outlook') }}</span>
                            <span class="text-[10px] text-slate-400 block">{{ __('Direct Webcal') }}</span>
                        </div>
                    </a>

                    <a
                        href="{{ $feedUrl }}"
                        download="bookings.ics"
                        class="p-3 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#151a26] hover:bg-[#F3F4F6] dark:hover:bg-[#1E2433] transition text-left flex flex-col justify-between group shadow-none"
                    >
                        <i class="fa-solid fa-file-arrow-down text-slate-700 dark:text-slate-300 text-lg"></i>
                        <div class="mt-2">
                            <span class="font-medium text-xs text-[#12181E] dark:text-white block group-hover:text-black dark:group-hover:text-white">{{ __('Download .ics') }}</span>
                            <span class="text-[10px] text-slate-400 block">{{ __('Offline File') }}</span>
                        </div>
                    </a>
                </div>

                @if ($isLocalHost)
                    <!-- Local environment advice -->
                    <div class="p-3 rounded-[8px] bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 text-xs text-amber-800 dark:text-amber-300 space-y-1">
                        <div class="flex items-center gap-1.5 font-medium">
                            <i class="fa-solid fa-circle-info text-amber-600"></i>
                            <span>{{ __('Local Development Host (:host)', ['host' => $feedHost]) }}</span>
                        </div>
                        <p class="text-[11px] leading-relaxed text-amber-700 dark:text-amber-400">
                            {{ __('Google Calendar cloud servers cannot resolve local domains (:host). Use "Apple / Outlook" or "Download .ics" for local testing. On production, Google Calendar subscribes automatically.', ['host' => $feedHost]) }}
                        </p>
                    </div>
                @endif

                <!-- Live Feed URL Box -->
                <div class="p-3.5 rounded-[8px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] space-y-2">
                    <span class="text-[10px] uppercase font-medium tracking-wider text-slate-400">
                        {{ __('Your Private Live Calendar Feed URL (iCal / .ics)') }}
                    </span>
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            readonly
                            value="{{ $feedUrl }}"
                            id="calendarFeedUrlInput"
                            class="h-9 w-full px-3 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] font-mono text-xs text-slate-700 dark:text-slate-300 select-all"
                        />
                        <button
                            type="button"
                            @click="
                                if (navigator.clipboard && navigator.clipboard.writeText) {
                                    navigator.clipboard.writeText('{{ $feedUrl }}');
                                } else {
                                    const el = document.getElementById('calendarFeedUrlInput');
                                    el.select();
                                    document.execCommand('copy');
                                }
                                copied = true;
                                setTimeout(() => copied = false, 2500)
                            "
                            class="h-9 px-3.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-medium text-xs shadow-none transition shrink-0 flex items-center gap-1.5 cursor-pointer"
                        >
                            <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                            <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</span>
                        </button>
                    </div>
                </div>

                <!-- How to Add Steps -->
                <div class="space-y-2 text-xs">
                    <h4 class="font-medium text-[#12181E] dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-circle-question text-slate-400"></i>
                        {{ __('Manual Subscription Steps:') }}
                    </h4>
                    <ol class="list-decimal list-inside space-y-1 text-slate-600 dark:text-slate-300 pl-1">
                        <li>{{ __('Copy the feed URL above.') }}</li>
                        <li>{{ __('In Google Calendar: click "+" next to "Other calendars" > "From URL".') }}</li>
                        <li>{{ __('In Apple Calendar (Mac/iOS): click "File" > "New Calendar Subscription".') }}</li>
                        <li>{{ __('In Microsoft Outlook: click "Add Calendar" > "Subscribe from web".') }}</li>
                    </ol>
                </div>
            @endif

            <!-- Modal Actions -->
            <div class="flex items-center justify-end pt-4 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                <x-button type="button" variant="secondary" size="sm" class="rounded-[6px] font-medium shadow-none" x-on:click="$dispatch('close-modal', 'google-calendar-sync')">
                    {{ __('Done') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
