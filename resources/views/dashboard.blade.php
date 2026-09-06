<x-layouts::app :title="__('Operator Dashboard')">
    @php
        /** @var \App\Models\Operator|null $operator */
        $operator = auth()->user()?->currentOperator();

        $packagesCount = $operator ? $operator->packages()->count() : 0;
        $productsCount = $operator ? $operator->products()->count() : 0;
        $reservationsCount = $operator ? $operator->reservations()->count() : 0;

        $pendingConfirmationCount = $operator
            ? $operator->reservations()->where('status', \App\Enums\ReservationStatus::PendingConfirmation)->count()
            : 0;

        $todayDepartures = $operator
            ? $operator
                ->reservations()
                ->with(['bookable', 'latestPayment'])
                ->whereDate('requested_date', now()->toDateString())
                ->whereIn('status', [
                    \App\Enums\ReservationStatus::Confirmed,
                    \App\Enums\ReservationStatus::PendingConfirmation,
                ])
                ->get()
            : collect();

        $upcomingDepartures = $operator
            ? $operator
                ->reservations()
                ->with(['bookable', 'latestPayment'])
                ->whereDate('requested_date', '>=', now()->toDateString())
                ->whereDate('requested_date', '<=', now()->addDays(7)->toDateString())
                ->whereIn('status', [
                    \App\Enums\ReservationStatus::Confirmed,
                    \App\Enums\ReservationStatus::PendingConfirmation,
                ])
                ->orderBy('requested_date', 'asc')
                ->take(5)
                ->get()
            : collect();

        $recentBookings = $operator
            ? $operator
                ->reservations()
                ->with(['bookable', 'latestPayment'])
                ->latest('created_at')
                ->take(6)
                ->get()
            : collect();

        $totalRevenue = $operator
            ? (float) \App\Models\Payment::where('status', \App\Enums\PaymentStatus::Paid)
                ->whereHas('reservation', fn ($q) => $q->where('operator_id', $operator->id))
                ->sum('amount')
            : 0.0;

        $totalPaxServed = $operator
            ? (int) $operator
                ->reservations()
                ->whereIn('status', [\App\Enums\ReservationStatus::Confirmed, \App\Enums\ReservationStatus::Completed])
                ->sum('pax_count')
            : 0;

        $totalGuestsCount = $operator ? $operator->guests()->count() : 0;

        $hasProfile = $operator && ! empty($operator->contact_whatsapp) && ! empty($operator->bio);
        $hasTerms = $operator && ! empty($operator->terms_and_conditions);
        $hasBank = $operator && $operator->hasPayoutBankAccount();
        $hasBillingEmail = $operator && filled($operator->billing_email);
        $hasNotificationEmail = $operator && filled($operator->booking_notification_email);
        $hasProducts = $productsCount > 0;
        $hasPackages = $packagesCount > 0;
        $storefrontIsPublic = $operator?->isStorefrontPublic() ?? false;

        $requiredSetup = [
            ['done' => $hasProfile, 'label' => __('Bio & WhatsApp'), 'url' => route('brand.edit')],
            ['done' => $hasTerms, 'label' => __('Guest booking terms'), 'url' => route('storefront-settings.edit')],
            ['done' => $hasBank, 'label' => __('Payout bank account'), 'url' => route('payments.edit')],
            ['done' => $hasBillingEmail, 'label' => __('Billing email'), 'url' => route('brand.edit')],
            ['done' => $hasNotificationEmail, 'label' => __('Booking notification email'), 'url' => route('brand.edit')],
        ];
        $completedSteps = collect($requiredSetup)->where('done', true)->count();
        $progressPercent = ($completedSteps / count($requiredSetup)) * 100;

        $platformDomain = app(\App\Services\DomainResolverService::class)->getPlatformDomain();
        $storefrontUrl = $operator ? request()->getScheme().'://'.$operator->slug.'.'.$platformDomain : '#';
        $isManualConfirmation = $operator?->isManualConfirmationEnabled() ?? false;
        $nextDeparture = $upcomingDepartures->first();
        $defaultBoard = $upcomingDepartures->isNotEmpty() ? 'upcoming' : 'recent';
    @endphp

    <div class="animate-fade-in space-y-5" x-data="{ copied: false, board: '{{ $defaultBoard }}' }">
        @if (session('welcome_onboarding') && $operator)
            <div class="flex flex-col gap-3 rounded-2xl border border-[#FFEF4D]/50 bg-[#FFEF4D]/15 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-[#FFEF4D]/20 dark:bg-[#FFEF4D]/10">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-op-ink">
                        {{ $storefrontIsPublic ? __('Your booking page is ready') : __('Your account is created') }}
                    </p>
                    <p class="mt-0.5 text-xs text-op-subtle">
                        {{ $storefrontIsPublic
                            ? __('Share the link with guests. Add a trip next.')
                            : __('Guests cannot open your page yet. Finish the setup list, then share your link.') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-button :href="$storefrontUrl" target="_blank" size="sm">
                        {{ __('Open your page') }}
                    </x-button>
                    <x-button :href="route('packages.create')" variant="secondary" size="sm" wire:navigate>
                        {{ __('Add a trip') }}
                    </x-button>
                </div>
            </div>
        @endif

        @if ($pendingConfirmationCount > 0)
            <div class="flex flex-col gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-amber-800/80 dark:bg-amber-950/40">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-op-ink">
                        {{ __(':count booking(s) waiting for confirmation', ['count' => $pendingConfirmationCount]) }}
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

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-metric-card
                :label="__('Direct Revenue')"
                :value="'Rp '.number_format($totalRevenue, 0, ',', '.')"
                icon="fa-rupiah-sign"
                tone="featured"
                :href="route('wallet.index')"
            >
                <x-slot:meta>
                    <span class="op-metric-chip inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold">
                        {{ __(':count bookings', ['count' => $reservationsCount]) }}
                    </span>
                </x-slot:meta>
            </x-metric-card>

            <x-metric-card
                :label="__('Today\'s trips')"
                :value="$todayDepartures->count()"
                :suffix="'('.((int) $todayDepartures->sum('pax_count')).' pax)'"
                :hint="now()->format('M d')"
                icon="fa-calendar-day"
                :href="route('calendar.index')"
            />

            <x-metric-card
                :label="__('Guests served')"
                :value="number_format($totalPaxServed)"
                :suffix="__('pax')"
                :hint="__(':count profiles', ['count' => $totalGuestsCount])"
                icon="fa-person-walking-luggage"
                :href="route('guests.index')"
            />

            <x-metric-card
                :label="__('Live catalog')"
                :value="$packagesCount"
                :suffix="__('packages')"
                :hint="__(':count activities', ['count' => $productsCount])"
                icon="fa-cubes"
                :href="route('packages.index')"
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
                        class="text-xs font-semibold text-op-subtle hover:text-op-ink"
                        wire:navigate
                        x-show="board === 'upcoming'"
                    >
                        {{ __('Open calendar') }}
                    </a>
                    <a
                        href="{{ route('reservations.index') }}"
                        class="text-xs font-semibold text-op-subtle hover:text-op-ink"
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
                                'flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl text-center',
                                'bg-brand-400 text-brand-foreground' => $isToday,
                                'bg-op-muted text-op-ink' => ! $isToday,
                            ])>
                                <span class="text-[9px] font-semibold uppercase">{{ $res->requested_date->format('M') }}</span>
                                <span class="text-sm font-bold leading-none">{{ $res->requested_date->format('d') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-op-ink">{{ $res->guest_name }}</p>
                                <p class="truncate text-xs text-op-subtle">
                                    #{{ $resCode }} · {{ $bookable->name ?? ($bookable->title ?? __('Tour')) }} · {{ __(':count pax', ['count' => $res->pax_count]) }}
                                </p>
                            </div>
                            <span class="hidden font-mono text-xs font-semibold text-op-ink sm:inline">
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
                                <p class="truncate text-sm font-semibold text-op-ink">{{ $res->guest_name }}</p>
                                <p class="truncate text-xs text-op-subtle">
                                    #{{ $resCode }} · {{ $res->requested_date->format('M d, Y') }} · {{ __(':count pax', ['count' => $res->pax_count]) }}
                                </p>
                            </div>
                            <span class="hidden font-mono text-xs font-semibold text-op-ink sm:inline">
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
                    <div class="op-card space-y-3 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-op-subtle">{{ __('Next departure') }}</p>
                        <p class="text-lg font-bold text-op-ink">{{ $nextDeparture->requested_date->format('D, M d') }}</p>
                        <p class="text-sm text-op-subtle">
                            {{ $nextDeparture->guest_name }} · {{ __(':count pax', ['count' => $nextDeparture->pax_count]) }}
                        </p>
                        <x-button :href="route('reservations.index')" variant="secondary" size="sm" class="w-full" wire:navigate>
                            {{ __('Open bookings') }}
                        </x-button>
                    </div>
                @endif

                <div class="op-card p-5">
                    <p class="mb-3 text-xs font-bold uppercase tracking-wider text-op-subtle">{{ __('Shortcuts') }}</p>
                    <div class="divide-y divide-op-line">
                        <a href="{{ route('packages.create') }}" class="flex items-center justify-between py-2.5 text-sm font-semibold text-op-ink first:pt-0" wire:navigate>
                            {{ __('Create package') }}
                            <i class="fa-solid fa-chevron-right text-[10px] text-op-subtle"></i>
                        </a>
                        <a href="{{ route('products.create') }}" class="flex items-center justify-between py-2.5 text-sm font-semibold text-op-ink" wire:navigate>
                            {{ __('Add activity') }}
                            <i class="fa-solid fa-chevron-right text-[10px] text-op-subtle"></i>
                        </a>
                        <a href="{{ route('calendar.index') }}" class="flex items-center justify-between py-2.5 text-sm font-semibold text-op-ink" wire:navigate>
                            {{ __('Availability') }}
                            <i class="fa-solid fa-chevron-right text-[10px] text-op-subtle"></i>
                        </a>
                        <a href="{{ route('guests.index') }}" class="flex items-center justify-between py-2.5 text-sm font-semibold text-op-ink last:pb-0" wire:navigate>
                            {{ __('Guest CRM') }}
                            <i class="fa-solid fa-chevron-right text-[10px] text-op-subtle"></i>
                        </a>
                    </div>
                </div>

                @if ($operator)
                    <div class="op-card space-y-2 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-op-subtle">{{ __('Your page') }}</p>
                        @if (! $storefrontIsPublic)
                            <p class="text-xs text-op-subtle">{{ __('Closed to guests until setup is finished.') }}</p>
                        @endif
                        <div class="flex items-center gap-2 rounded-xl bg-op-muted px-2.5 py-2">
                            <span class="min-w-0 flex-1 truncate font-mono text-[11px] text-op-ink select-all">{{ $storefrontUrl }}</span>
                            @if ($storefrontIsPublic)
                                <button
                                    type="button"
                                    class="shrink-0 cursor-pointer rounded-lg bg-brand-400 px-2.5 py-1 text-xs font-semibold text-brand-foreground"
                                    x-on:click="navigator.clipboard.writeText('{{ $storefrontUrl }}'); copied = true; setTimeout(() => copied = false, 2500)"
                                >
                                    <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'"></span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($completedSteps < count($requiredSetup) || ! ($hasProducts && $hasPackages))
                    <div class="op-card space-y-3 p-5">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-bold uppercase tracking-wider text-op-subtle">{{ __('Setup') }}</p>
                            <p class="text-xs font-semibold text-op-ink">{{ $completedSteps }}/{{ count($requiredSetup) }}</p>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-op-muted">
                            <div class="h-full rounded-full bg-brand-400" style="width: {{ $progressPercent }}%;"></div>
                        </div>
                        <div class="space-y-2 text-xs">
                            @foreach ($requiredSetup as $step)
                                <a href="{{ $step['url'] }}" class="flex cursor-pointer items-center justify-between hover:text-op-ink" wire:navigate>
                                    <span @class(['text-op-subtle line-through' => $step['done'], 'font-semibold text-op-ink' => ! $step['done']])>{{ $step['label'] }}</span>
                                    <i @class(['fa-solid', 'fa-check text-emerald-500' => $step['done'], 'fa-circle text-op-line' => ! $step['done']])></i>
                                </a>
                            @endforeach
                            <a href="{{ route('packages.create') }}" class="flex cursor-pointer items-center justify-between hover:text-op-ink" wire:navigate>
                                <span @class(['text-op-subtle line-through' => $hasProducts && $hasPackages, 'font-semibold text-op-ink' => ! ($hasProducts && $hasPackages)])>{{ __('Add a trip') }}</span>
                                <i @class(['fa-solid', 'fa-check text-emerald-500' => $hasProducts && $hasPackages, 'fa-circle text-op-line' => ! ($hasProducts && $hasPackages)])></i>
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts::app>
