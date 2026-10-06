<x-layouts::app :title="__('Operator Dashboard')">
    @php
        /** @var \App\Models\Operator|null $operator */
        $operator = auth()->user()?->currentOperator();

        $summary = $operator ? app(\App\Services\OperatorDashboardService::class)->summary($operator) : null;

        $earningsThisMonth = $summary['earnings_this_month'] ?? 0.0;
        $earningsChange = $summary['earnings_change_pct'] ?? null;
        $earningsHint = match (true) {
            $earningsChange !== null => ($earningsChange >= 0 ? '+' : '').round($earningsChange).'% '.__('vs same days last month'),
            $earningsThisMonth > 0 => __('New this month'),
            default => __('No earnings last month'),
        };

        $upcomingGuests7Days = $summary['upcoming_guests_7d'] ?? 0;
        $upcomingTrips7Days = $summary['upcoming_trips_7d'] ?? 0;

        $waitingConfirmationCount = $summary['pending_confirmation_count'] ?? 0;
        $waitingPaymentCount = $summary['open_pay_links_count'] ?? 0;
        $totalWaitingCount = $summary['awaiting_action'] ?? 0;
        $waitingHint = match (true) {
            $waitingConfirmationCount > 0 && $waitingPaymentCount > 0 => __(':conf to confirm, :pay open links', ['conf' => $waitingConfirmationCount, 'pay' => $waitingPaymentCount]),
            $waitingConfirmationCount > 0 => trans_choice(':count booking to confirm|:count bookings to confirm', $waitingConfirmationCount, ['count' => $waitingConfirmationCount]),
            $waitingPaymentCount > 0 => trans_choice(':count open pay link|:count open pay links', $waitingPaymentCount, ['count' => $waitingPaymentCount]),
            default => __('All caught up'),
        };

        $availableBalance = $summary['available_balance'] ?? 0.0;
        $todayDepartures = $summary['today_departures'] ?? collect();
        $upcomingDepartures = $summary['upcoming_departures'] ?? collect();
        $recentBookings = $summary['recent_bookings'] ?? collect();
        $sevenDayStrip = collect($summary['seven_day_schedule'] ?? []);

        $storefrontIsPublic = $operator?->isStorefrontPublic() ?? false;
        $platformDomain = app(\App\Services\DomainResolverService::class)->getPlatformDomain();
        $storefrontUrl = $operator ? request()->getScheme().'://'.$operator->slug.'.'.$platformDomain : '#';
        $isManualConfirmation = $operator?->isManualConfirmationEnabled() ?? false;
        $nextDeparture = $upcomingDepartures->first();
        $defaultBoard = $upcomingDepartures->isNotEmpty() ? 'upcoming' : 'recent';
    @endphp

    <div class="animate-fade-in space-y-5" x-data="{ copied: false, board: '{{ $defaultBoard }}' }">
        @if ($waitingConfirmationCount > 0)
            <div class="flex flex-col gap-3 rounded-[12px] border border-amber-300 bg-amber-50/60 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-800/80 dark:bg-amber-950/30">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-op-ink">
                        {{ __(':count booking(s) waiting for confirmation', ['count' => $waitingConfirmationCount]) }}
                    </p>
                    <p class="mt-0.5 text-xs text-op-subtle">
                        {{ __('Guests have paid and are waiting for you to confirm their slots.') }}
                    </p>
                </div>
                <x-button :href="route('reservations.index')" size="sm" wire:navigate>
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>{{ __('Review bookings') }}</span>
                </x-button>
            </div>
        @endif

        <div class="op-hero">
            <x-page-header
                :title="__('Welcome back, :name!', ['name' => auth()->user()->name])"
                :subtitle="($operator?->name ?? __('Tour Operator')).' · '.($isManualConfirmation ? __('Manual approval') : __('Auto-confirm'))"
            >
                @if ($operator)
                    <x-slot:actions>
                        <x-button :href="$storefrontUrl" target="_blank" size="sm" :variant="$storefrontIsPublic ? 'primary' : 'secondary'">
                            <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                            <span>{{ $storefrontIsPublic ? __('Live Storefront') : __('Page is closed') }}</span>
                        </x-button>
                        <x-button
                            type="button"
                            variant="secondary"
                            size="sm"
                            x-on:click="navigator.clipboard.writeText('{{ $storefrontUrl }}'); copied = true; setTimeout(() => copied = false, 2500)"
                        >
                            <i class="fa-solid" :class="copied ? 'fa-check text-emerald-500' : 'fa-copy'"></i>
                            <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy link') }}'"></span>
                        </x-button>
                    </x-slot:actions>
                @endif
            </x-page-header>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-metric-card
                :label="__('Earnings this month')"
                :value="'Rp '.number_format($earningsThisMonth, 0, ',', '.')"
                :hint="$earningsHint"
                icon="fa-rupiah-sign"
                tone="featured"
                :href="route('wallet.index')"
            />

            <x-metric-card
                :label="__('Upcoming guests (7 days)')"
                :value="$upcomingGuests7Days"
                :suffix="__('pax')"
                :hint="__(':count trips planned', ['count' => $upcomingTrips7Days])"
                icon="fa-calendar-week"
                :href="route('calendar.index')"
            />

            <x-metric-card
                :label="__('Waiting for you')"
                :value="$totalWaitingCount"
                :hint="$waitingHint"
                icon="fa-inbox"
                :tone="$totalWaitingCount > 0 ? 'accent' : 'default'"
                :href="route('reservations.index')"
            />

            <x-metric-card
                :label="__('Available to pay out')"
                :value="'Rp '.number_format($availableBalance, 0, ',', '.')"
                :hint="__('Ready for withdrawal')"
                icon="fa-wallet"
                :tone="$availableBalance > 0 ? 'featured' : 'default'"
                :href="route('wallet.index')"
            />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            <div class="op-card lg:col-span-8">
                <div class="flex flex-col gap-3 border-b border-op-line p-4 sm:flex-row sm:items-center sm:justify-between">
                    <x-filter-tabs>
                        <x-filter-tab type="button" x-on:click="board = 'upcoming'" x-bind:aria-current="board === 'upcoming' ? 'page' : null">
                            {{ __('Upcoming') }}
                            <span class="text-op-subtle">{{ $upcomingDepartures->count() }}</span>
                        </x-filter-tab>
                        <x-filter-tab type="button" x-on:click="board = 'recent'" x-bind:aria-current="board === 'recent' ? 'page' : null">
                            {{ __('Recent bookings') }}
                        </x-filter-tab>
                    </x-filter-tabs>

                    <a
                        href="{{ route('calendar.index') }}"
                        class="text-xs font-medium text-op-subtle hover:text-op-ink"
                        wire:navigate
                        x-show="board === 'upcoming'"
                    >
                        {{ __('Open calendar') }}
                    </a>
                    <a
                        href="{{ route('reservations.index') }}"
                        class="text-xs font-medium text-op-subtle hover:text-op-ink"
                        wire:navigate
                        x-show="board === 'recent'"
                        x-cloak
                    >
                        {{ __('All bookings') }}
                    </a>
                </div>

                <div class="divide-y divide-op-line" x-show="board === 'upcoming'">
                    @forelse ($upcomingDepartures as $res)
                        @php
                            $bookable = $res->bookable;
                            $payment = $res->latestPayment;
                            $isToday = $res->requested_date->isToday();
                            $resCode = $res->code ?? 'RSV-'.strtoupper(substr($res->id, -8));
                        @endphp
                        <div class="flex items-center gap-3 px-4 py-3">
                            <div @class([
                                'flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-[8px] text-center',
                                'bg-[#FFEF4D] text-[#12181E]' => $isToday,
                                'bg-op-muted text-op-ink' => ! $isToday,
                            ])>
                                <span class="text-[9px] font-medium uppercase">{{ $res->requested_date->format('M') }}</span>
                                <span class="text-sm font-medium leading-none">{{ $res->requested_date->format('d') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-op-ink">{{ $res->guest_name }}</p>
                                <p class="truncate text-xs text-op-subtle">
                                    #{{ $resCode }} · {{ $bookable->name ?? ($bookable->title ?? __('Tour')) }} · {{ __(':count pax', ['count' => $res->pax_count]) }}
                                </p>
                            </div>
                            <span class="hidden font-mono text-xs font-medium text-op-ink sm:inline">
                                {{ $payment && $payment->isPaid() ? 'Rp '.number_format((float) $payment->amount, 0, ',', '.') : '—' }}
                            </span>
                        </div>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-op-subtle">
                            {{ __('No departures in the next 7 days.') }}
                        </p>
                    @endforelse
                </div>

                <div class="divide-y divide-op-line" x-show="board === 'recent'" x-cloak>
                    @forelse ($recentBookings as $res)
                        @php
                            $payment = $res->latestPayment;
                            $resCode = $res->code ?? 'RSV-'.strtoupper(substr($res->id, -8));
                        @endphp
                        <div class="flex items-center gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-op-ink">{{ $res->guest_name }}</p>
                                <p class="truncate text-xs text-op-subtle">
                                    #{{ $resCode }} · {{ $res->requested_date->format('M d, Y') }} · {{ __(':count pax', ['count' => $res->pax_count]) }}
                                </p>
                            </div>
                            <span class="hidden font-mono text-xs font-medium text-op-ink sm:inline">
                                {{ $payment && $payment->isPaid() ? 'Rp '.number_format((float) $payment->amount, 0, ',', '.') : '—' }}
                            </span>
                            <x-status-badge :status="$res->status" />
                        </div>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-op-subtle">
                            {{ __('No bookings yet. Share your storefront to get the first one.') }}
                        </p>
                    @endforelse
                </div>
            </div>

            <div class="space-y-4 lg:col-span-4">
                @if ($nextDeparture)
                    <div class="op-card space-y-3 p-4 sm:p-5">
                        <p class="text-xs font-medium uppercase tracking-wider text-op-subtle">{{ __('Next departure') }}</p>
                        <p class="text-lg font-medium text-op-ink">{{ $nextDeparture->requested_date->format('D, M d') }}</p>
                        <p class="text-sm text-op-subtle">
                            {{ $nextDeparture->guest_name }} · {{ __(':count pax', ['count' => $nextDeparture->pax_count]) }}
                        </p>
                        <x-button :href="route('reservations.index')" variant="secondary" size="sm" class="w-full" wire:navigate>
                            {{ __('Open bookings') }}
                        </x-button>
                    </div>
                @endif

                <div class="op-card p-4 sm:p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-medium uppercase tracking-wider text-op-subtle">{{ __('7-Day Schedule') }}</p>
                        <a href="{{ route('calendar.index') }}" class="text-xs font-medium text-op-subtle hover:text-op-ink" wire:navigate>
                            {{ __('Open calendar') }}
                        </a>
                    </div>
                    <div class="divide-y divide-op-line">
                        @foreach ($sevenDayStrip as $day)
                            <div class="flex items-center justify-between py-2 text-xs first:pt-0 last:pb-0">
                                <div class="flex items-center gap-2">
                                    <span @class([
                                        'w-7 text-center font-bold text-[11px] rounded px-1 py-0.5',
                                        'bg-[#FFEF4D] text-[#12181E]' => $day['isToday'],
                                        'text-op-subtle' => ! $day['isToday'],
                                    ])>
                                        {{ $day['date']->format('D') }}
                                    </span>
                                    <span class="text-op-ink font-medium">{{ $day['date']->format('M d') }}</span>
                                </div>
                                <div>
                                    @if ($day['pax'] > 0)
                                        <span class="inline-flex items-center gap-1 rounded bg-op-muted px-1.5 py-0.5 font-medium text-op-ink text-[11px]">
                                            <i class="fa-solid fa-users text-[10px] text-op-subtle"></i>
                                            {{ __(':pax pax', ['pax' => $day['pax']]) }}
                                        </span>
                                    @else
                                        <span class="text-op-subtle text-[11px]">{{ __('Open') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($operator)
                    <div class="op-card space-y-2 p-4 sm:p-5">
                        <p class="text-xs font-medium uppercase tracking-wider text-op-subtle">{{ __('Your page') }}</p>
                        @if (! $storefrontIsPublic)
                            <p class="text-xs text-op-subtle">{{ __('Closed to guests until setup is finished.') }}</p>
                        @endif
                        <div class="flex items-center gap-2 rounded-[6px] border border-op-line bg-op-muted px-2.5 py-1.5">
                            <span class="min-w-0 flex-1 truncate font-mono text-[11px] text-op-ink select-all">{{ $storefrontUrl }}</span>
                            @if ($storefrontIsPublic)
                                <button
                                    type="button"
                                    class="shrink-0 cursor-pointer rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] px-2.5 py-1 text-xs font-medium text-[#12181E] transition"
                                    x-on:click="navigator.clipboard.writeText('{{ $storefrontUrl }}'); copied = true; setTimeout(() => copied = false, 2500)"
                                >
                                    <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'"></span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts::app>
