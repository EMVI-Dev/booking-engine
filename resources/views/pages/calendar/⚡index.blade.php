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

<div class="space-y-8 animate-fade-in print:space-y-0">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 print:hidden">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">
                <span>{{ __('Operations Hub') }}</span>
                <span>&bull;</span>
                <span class="text-slate-800 dark:text-[#FFEF4D] font-bold">{{ __('Calendar & Operations Schedule') }}</span>
            </div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-[#FFEF4D] text-[#090d16] dark:bg-indigo-950/70 dark:text-indigo-400">
                    <i class="fa-solid fa-calendar-days text-lg"></i>
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    {{ __('Booking Calendar & Availability') }}
                </h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Manage scheduled guest departures, resource allocation, run-sheet manifests, and blackout dates.') }}
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <!-- Google / Apple Calendar Sync Button -->
            <button
                type="button"
                wire:click="openGoogleSyncModal"
                class="h-10 px-4 rounded-xl bg-white dark:bg-zinc-900 hover:bg-slate-50 dark:hover:bg-zinc-800 border border-slate-200/80 dark:border-zinc-800 text-slate-700 dark:text-zinc-200 font-bold text-xs shadow-2xs transition flex items-center gap-2 cursor-pointer"
            >
                <i class="fa-brands fa-google text-indigo-600 dark:text-indigo-400 text-sm"></i>
                <span>{{ __('Sync iCal Feed') }}</span>
                @if (!$this->hasGoogleCalendarFeature)
                    <span title="{{ __('Requires Pro Operator Plan') }}" class="px-1.5 py-0.2 rounded-md text-[9px] font-black uppercase bg-[#FFEF4D] text-[#090d16] flex items-center gap-1">
                        <i class="fa-solid fa-lock text-[8px]"></i>
                        <span>PRO</span>
                    </span>
                @endif
            </button>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if (session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center gap-2 animate-fade-in shadow-xs print:hidden">
            <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400 text-sm"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Calendar View Mode Tabs Navigator -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-200/80 dark:border-[#1e2433] pb-3 print:hidden">
        <div class="flex items-center gap-1.5 p-1 rounded-2xl bg-slate-100 dark:bg-[#141721] border border-slate-200 dark:border-[#262d3d] overflow-x-auto max-w-full">
            <!-- 1. Month Grid View (Free) -->
            <button
                type="button"
                wire:click="switchView('month')"
                class="px-3.5 py-2 rounded-xl text-xs font-black transition-all cursor-pointer flex items-center gap-1.5 {{ $viewMode === 'month' ? 'bg-[#FFEF4D] text-[#090d16] shadow-xs' : 'text-slate-600 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white' }}"
            >
                <i class="fa-solid fa-calendar-days"></i>
                <span>{{ __('Month Grid') }}</span>
            </button>

            <!-- 2. Resource Timeline (Pro) -->
            <button
                type="button"
                wire:click="switchView('timeline')"
                class="px-3.5 py-2 rounded-xl text-xs font-black transition-all cursor-pointer flex items-center gap-1.5 {{ $viewMode === 'timeline' ? 'bg-[#FFEF4D] text-[#090d16] shadow-xs' : 'text-slate-600 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white' }}"
            >
                <i class="fa-solid fa-bars-staggered"></i>
                <span>{{ __('Resource Timeline') }}</span>
                @if (!$this->hasTimelineFeature)
                    <span title="{{ __('Requires Pro Operator Plan') }}" class="px-1.5 py-0.2 rounded-md text-[9px] font-black uppercase bg-[#FFEF4D] text-[#090d16] border border-[#fae639] flex items-center gap-1">
                        <i class="fa-solid fa-lock text-[8px]"></i>
                        <span>PRO</span>
                    </span>
                @endif
            </button>

            <!-- 3. Daily Run-Sheet & Manifest (Pro) -->
            <button
                type="button"
                wire:click="switchView('manifest')"
                class="px-3.5 py-2 rounded-xl text-xs font-black transition-all cursor-pointer flex items-center gap-1.5 {{ $viewMode === 'manifest' ? 'bg-[#FFEF4D] text-[#090d16] shadow-xs' : 'text-slate-600 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white' }}"
            >
                <i class="fa-solid fa-clipboard-list"></i>
                <span>{{ __('Daily Manifest') }}</span>
                @if (!$this->hasManifestFeature)
                    <span title="{{ __('Requires Pro Operator Plan') }}" class="px-1.5 py-0.2 rounded-md text-[9px] font-black uppercase bg-[#FFEF4D] text-[#090d16] border border-[#fae639] flex items-center gap-1">
                        <i class="fa-solid fa-lock text-[8px]"></i>
                        <span>PRO</span>
                    </span>
                @endif
            </button>

            <!-- 4. Occupancy Heatmap (Ultimate) -->
            <button
                type="button"
                wire:click="switchView('heatmap')"
                class="px-3.5 py-2 rounded-xl text-xs font-black transition-all cursor-pointer flex items-center gap-1.5 {{ $viewMode === 'heatmap' ? 'bg-[#FFEF4D] text-[#090d16] shadow-xs' : 'text-slate-600 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white' }}"
            >
                <i class="fa-solid fa-fire-flame-curved"></i>
                <span>{{ __('Capacity Heatmap') }}</span>
                @if (!$this->hasHeatmapFeature)
                    <span title="{{ __('Requires Agency Ultimate Plan') }}" class="px-1.5 py-0.2 rounded-md text-[9px] font-black uppercase bg-[#FFEF4D] text-[#090d16] border border-[#fae639] flex items-center gap-1">
                        <i class="fa-solid fa-lock text-[8px]"></i>
                        <span>ULTIMATE</span>
                    </span>
                @endif
            </button>
        </div>
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
                    requiredPlan="Pro Operator"
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
                    requiredPlan="Pro Operator"
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
                    requiredPlan="Agency Ultimate"
                    planSlug="enterprise"
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

    <!-- Google / Apple Calendar Live Sync Modal -->
    <x-modal name="google-calendar-sync" maxWidth="lg">
        @php
            $feedToken = $this->currentOperator?->getCalendarFeedToken() ?? '';
            $feedUrl = $feedToken ? route('calendar.feed', ['token' => $feedToken]) : '';
        @endphp
        <div class="p-6 space-y-5" x-data="{ copied: false }">
            <div class="flex items-center gap-3">
                <span class="p-3 rounded-2xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-lg">
                    <i class="fa-brands fa-google"></i>
                </span>
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                        {{ __('Sync with Google & Apple Calendar') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Automatically display incoming reservations in your personal calendar in real-time.') }}
                    </p>
                </div>
            </div>

            <!-- Live Feed URL Box -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200 dark:border-zinc-800 space-y-2">
                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400">
                    {{ __('Your Private Live Calendar Feed URL (iCal / .ics)') }}
                </span>
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        readonly
                        value="{{ $feedUrl }}"
                        id="calendarFeedUrlInput"
                        class="h-9 w-full px-3 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 font-mono text-xs text-slate-700 dark:text-slate-300 select-all"
                    />
                    <button
                        type="button"
                        @click="navigator.clipboard.writeText('{{ $feedUrl }}'); copied = true; setTimeout(() => copied = false, 2500)"
                        class="h-9 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-xs shadow-2xs transition shrink-0 flex items-center gap-1.5 cursor-pointer"
                    >
                        <i class="fa-solid" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                        <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</span>
                    </button>
                </div>
            </div>

            <!-- How to Add to Google Calendar Steps -->
            <div class="space-y-3 text-xs">
                <h4 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-circle-info text-indigo-500"></i>
                    {{ __('How to subscribe in Google Calendar:') }}
                </h4>
                <ol class="list-decimal list-inside space-y-1.5 text-slate-600 dark:text-slate-300 pl-1">
                    <li>{{ __('Open Google Calendar on your browser.') }}</li>
                    <li>{{ __('On the left sidebar, click the "+" icon next to "Other calendars".') }}</li>
                    <li>{{ __('Select "From URL".') }}</li>
                    <li>{{ __('Paste the feed URL copied above and click "Add calendar".') }}</li>
                </ol>
            </div>

            <!-- Modal Actions -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-zinc-800">
                <a
                    href="https://calendar.google.com/calendar/r/settings/addbyurl"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                    <span>{{ __('Open Google Calendar Settings') }}</span>
                </a>

                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'google-calendar-sync')">
                    {{ __('Done') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
