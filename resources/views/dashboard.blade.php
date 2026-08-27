<x-layouts::app :title="__('Operator Dashboard')">
    @php
        /** @var \App\Models\Operator|null $operator */
        $operator = auth()->user()?->currentOperator();

        $packagesCount = $operator ? $operator->packages()->count() : 0;
        $productsCount = $operator ? $operator->products()->count() : 0;
        $reservationsCount = $operator ? $operator->reservations()->count() : 0;

        $pendingConfirmationCount = $operator ? $operator->reservations()->where('status', \App\Enums\ReservationStatus::PendingConfirmation)->count() : 0;

        $todayDepartures = $operator ? $operator->reservations()
            ->with(['bookable', 'latestPayment'])
            ->whereDate('requested_date', now()->toDateString())
            ->whereIn('status', [\App\Enums\ReservationStatus::Confirmed, \App\Enums\ReservationStatus::PendingConfirmation])
            ->get() : collect();

        $upcomingDepartures = $operator ? $operator->reservations()
            ->with(['bookable', 'latestPayment'])
            ->whereDate('requested_date', '>=', now()->toDateString())
            ->whereDate('requested_date', '<=', now()->addDays(7)->toDateString())
            ->whereIn('status', [\App\Enums\ReservationStatus::Confirmed, \App\Enums\ReservationStatus::PendingConfirmation])
            ->orderBy('requested_date', 'asc')
            ->take(5)
            ->get() : collect();

        $recentBookings = $operator ? $operator->reservations()
            ->with(['bookable', 'latestPayment'])
            ->latest('created_at')
            ->take(6)
            ->get() : collect();

        // Single correlated subquery — no PHP-side pluck() needed
        $totalRevenue = $operator ? (float) \App\Models\Payment::where('status', \App\Enums\PaymentStatus::Paid)
            ->whereHas('reservation', fn ($q) => $q->where('operator_id', $operator->id))
            ->sum('amount') : 0.0;

        $totalPaxServed = $operator ? (int) $operator->reservations()
            ->whereIn('status', [\App\Enums\ReservationStatus::Confirmed, \App\Enums\ReservationStatus::Completed])
            ->sum('pax_count') : 0;

        $totalGuestsCount = $operator ? $operator->guests()->count() : 0;

        // Onboarding checklist calculation
        $hasProfile = $operator && !empty($operator->contact_whatsapp) && !empty($operator->bio);
        $hasTerms = $operator && !empty($operator->terms_and_conditions);
        $hasBank = $operator && (!empty($operator->formatted_bank_account) || $operator->hasCustomPaymentGateway());
        $hasProducts = $productsCount > 0;
        $hasPackages = $packagesCount > 0;
        $isProfileComplete = $operator?->isProfileComplete() ?? false;

        $completedSteps = ($hasProfile ? 1 : 0) + ($hasTerms ? 1 : 0) + ($hasBank ? 1 : 0) + (($hasProducts && $hasPackages) ? 1 : 0);
        $progressPercent = ($completedSteps / 4) * 100;

        $platformDomain = app(\App\Services\DomainResolverService::class)->getPlatformDomain();
        $storefrontUrl = $operator ? (request()->getScheme() . '://' . $operator->slug . '.' . $platformDomain) : '#';
        $isManualConfirmation = $operator?->isManualConfirmationEnabled() ?? false;
    @endphp

    <div class="space-y-6 animate-fade-in" x-data="{ copied: false }">
        <!-- Needs Confirmation Alert Banner (if any) -->
        @if ($pendingConfirmationCount > 0)
            <div class="rounded-3xl p-4 sm:p-5 bg-gradient-to-r from-amber-500/15 via-amber-500/10 to-transparent border border-amber-300 dark:border-amber-900/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm animate-pulse">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-md">
                        <i class="fa-solid fa-bell text-base"></i>
                    </div>
                    <div class="space-y-0.5">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>{{ __(':count Booking(s) Awaiting Confirmation', ['count' => $pendingConfirmationCount]) }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-200 text-amber-900 dark:bg-amber-950 dark:text-amber-300">
                                {{ __('Action Required') }}
                            </span>
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            {{ __('Guests have completed verified payments and are waiting for your approval to confirm their tour slots.') }}
                        </p>
                    </div>
                </div>
                <a
                    href="{{ route('reservations.index') }}"
                    class="h-9 px-4 inline-flex items-center gap-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-sm shrink-0"
                    wire:navigate
                >
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>{{ __('Review Bookings (:count)', ['count' => $pendingConfirmationCount]) }}</span>
                </a>
            </div>
        @endif

        <!-- Welcome Hero Banner -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 sm:p-8 text-white shadow-xl border border-slate-800/60">
            <div class="absolute right-0 top-0 -mt-12 -mr-12 w-96 h-96 rounded-full bg-indigo-500/15 blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-5 sm:gap-6">
                <div class="space-y-2.5 max-w-xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-white/10 backdrop-blur-md border border-white/10 text-indigo-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            {{ $operator ? $operator->name : 'Tour Operator' }}
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $isManualConfirmation ? 'bg-amber-400/20 text-amber-300 border border-amber-400/30' : 'bg-emerald-400/20 text-emerald-300 border border-emerald-400/30' }}">
                            <i class="fa-solid {{ $isManualConfirmation ? 'fa-user-check' : 'fa-bolt' }} mr-1 text-[10px]"></i>
                            {{ $isManualConfirmation ? __('Manual Approval Mode') : __('Instant Auto-Confirmation') }}
                        </span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                        {{ __('Welcome back, :name!', ['name' => auth()->user()->name]) }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                        {{ __('Track live tour schedules, review guest reservations, and manage your direct booking storefront.') }}
                    </p>
                </div>

                @if ($operator)
                    <div class="grid grid-cols-2 sm:flex items-center gap-2.5 w-full sm:w-auto shrink-0">
                        <a
                            href="{{ $storefrontUrl }}"
                            target="_blank"
                            class="h-10 px-4 inline-flex items-center justify-center gap-2 rounded-2xl bg-white text-slate-900 hover:bg-slate-100 font-bold text-xs shadow-md transition"
                        >
                            <i class="fa-solid fa-arrow-up-right-from-square text-indigo-600 text-xs"></i>
                            <span>{{ __('Live Storefront') }}</span>
                        </a>

                        <button
                            type="button"
                            x-on:click="navigator.clipboard.writeText('{{ $storefrontUrl }}'); copied = true; setTimeout(() => copied = false, 2500)"
                            class="h-10 px-4 inline-flex items-center justify-center gap-2 rounded-2xl bg-white/15 hover:bg-white/25 text-white border border-white/20 text-xs font-semibold backdrop-blur-md transition cursor-pointer"
                        >
                            <i class="fa-solid" :class="copied ? 'fa-check text-emerald-400' : 'fa-copy text-slate-300'"></i>
                            <span x-text="copied ? '{{ __('Link Copied!') }}' : '{{ __('Copy Link') }}'"></span>
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <!-- 4 Key Operational Metrics Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <!-- Metric 1: Lifetime Revenue -->
            <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ __('Direct Revenue') }}
                    </span>
                    <span class="p-1.5 sm:p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 text-xs">
                        <i class="fa-solid fa-rupiah-sign"></i>
                    </span>
                </div>
                <p class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white truncate">
                    Rp {{ number_format($totalRevenue, 0, ',', '.') }}
                </p>
                <p class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1 truncate">
                    <i class="fa-solid fa-circle-check text-emerald-500 text-[10px]"></i>
                    {{ __(':count Total Bookings', ['count' => $reservationsCount]) }}
                </p>
            </div>

            <!-- Metric 2: Today's Departures -->
            <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ __('Today\'s Trips') }}
                    </span>
                    <span class="p-1.5 sm:p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 text-xs">
                        <i class="fa-solid fa-calendar-day"></i>
                    </span>
                </div>
                <div class="flex items-baseline gap-1.5 sm:gap-2">
                    <p class="text-xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        {{ $todayDepartures->count() }}
                    </p>
                    <span class="text-[11px] sm:text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        ({{ (int) $todayDepartures->sum('pax_count') }} Pax)
                    </span>
                </div>
                <p class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 truncate">
                    {{ __('Departing today (:date)', ['date' => now()->format('M d')]) }}
                </p>
            </div>

            <!-- Metric 3: Total Passengers Served & Guests -->
            <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ __('Guests Served') }}
                    </span>
                    <span class="p-1.5 sm:p-2 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 text-xs">
                        <i class="fa-solid fa-person-walking-luggage"></i>
                    </span>
                </div>
                <div class="flex items-baseline gap-1.5 sm:gap-2">
                    <p class="text-xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        {{ number_format($totalPaxServed) }}
                    </p>
                    <span class="text-[11px] sm:text-xs font-bold text-slate-400">
                        {{ __('Pax') }}
                    </span>
                </div>
                <p class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 truncate">
                    {{ __(':count Distinct Guest Profiles', ['count' => $totalGuestsCount]) }}
                </p>
            </div>

            <!-- Metric 4: Published Catalog Items -->
            <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ __('Live Catalog') }}
                    </span>
                    <span class="p-1.5 sm:p-2 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 text-xs">
                        <i class="fa-solid fa-cubes"></i>
                    </span>
                </div>
                <div class="flex items-baseline gap-1.5 sm:gap-2">
                    <p class="text-xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        {{ $packagesCount }}
                    </p>
                    <span class="text-[11px] sm:text-xs font-bold text-slate-400">
                        {{ __('Packages') }}
                    </span>
                </div>
                <p class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 truncate">
                    {{ __(':count Inventory Activities', ['count' => $productsCount]) }}
                </p>
            </div>
        </div>

        <!-- Two Columns Layout: Operations (8 cols) & Platform Management (4 cols) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Column: Upcoming Departures & Recent Activity (8 cols) -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Card: Upcoming Departures (Next 7 Days) -->
                <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs p-5 sm:p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                        <div class="flex items-center gap-3">
                            <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 text-xs">
                                <i class="fa-solid fa-calendar-week"></i>
                            </span>
                            <div>
                                <h3 class="font-bold text-base text-slate-900 dark:text-white">
                                    {{ __('Upcoming Departures (Next 7 Days)') }}
                                </h3>
                                <p class="text-xs text-slate-400">
                                    {{ __('Immediate upcoming guest tour schedule & capacity.') }}
                                </p>
                            </div>
                        </div>

                        <a href="{{ route('calendar.index') }}" wire:navigate class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                            <span>{{ __('Open Calendar') }}</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>

                    <div class="space-y-3">
                        @forelse ($upcomingDepartures as $res)
                            @php
                                $bookable = $res->bookable;
                                $payment = $res->latestPayment;
                                $isToday = $res->requested_date->isToday();
                                $isTomorrow = $res->requested_date->isTomorrow();
                                $resCode = $res->code ?? ('RSV-' . strtoupper(substr($res->id, -8)));
                            @endphp
                            <div class="p-3.5 sm:p-4 rounded-2xl border transition-all duration-150 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 {{ $isToday ? 'bg-indigo-50/40 dark:bg-indigo-950/20 border-indigo-200 dark:border-indigo-900/60' : 'bg-slate-50/50 dark:bg-zinc-800/40 border-slate-200 dark:border-zinc-800' }}">
                                <div class="flex items-start sm:items-center gap-3 min-w-0 w-full sm:w-auto">
                                    <!-- Date Badge -->
                                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl flex flex-col items-center justify-center shrink-0 text-center font-bold {{ $isToday ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-slate-200' }}">
                                        <span class="text-[9px] sm:text-[10px] uppercase leading-none">{{ $res->requested_date->format('M') }}</span>
                                        <span class="text-sm sm:text-base leading-tight font-extrabold">{{ $res->requested_date->format('d') }}</span>
                                    </div>

                                    <div class="space-y-1 min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="font-mono text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 px-1.5 py-0.2 rounded shrink-0">
                                                #{{ $resCode }}
                                            </span>
                                            <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white truncate max-w-[130px] xs:max-w-[200px]">
                                                {{ $res->guest_name }}
                                            </span>
                                            @if ($isToday)
                                                <span class="px-1.5 py-0.2 rounded-full text-[9px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 animate-pulse shrink-0">
                                                    {{ __('Today') }}
                                                </span>
                                            @elseif ($isTomorrow)
                                                <span class="px-1.5 py-0.2 rounded-full text-[9px] font-bold uppercase bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300 shrink-0">
                                                    {{ __('Tomorrow') }}
                                                </span>
                                            @endif
                                        </div>

                                        <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 truncate">
                                            <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $bookable->name ?? ($bookable->title ?? __('Tour Booking')) }}</span>
                                            &bull;
                                            <span>{{ __(':count Pax', ['count' => $res->pax_count]) }}</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between sm:justify-end gap-2.5 w-full sm:w-auto border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-100 dark:border-zinc-800 shrink-0">
                                    <span class="text-xs font-bold text-slate-900 dark:text-white">
                                        {{ $payment && $payment->isPaid() ? 'Rp ' . number_format((float) $payment->amount, 0, ',', '.') : '—' }}
                                    </span>
                                    <a
                                        href="{{ route('reservations.index') }}"
                                        wire:navigate
                                        class="h-7 sm:h-8 px-2.5 sm:px-3 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-zinc-700 text-xs font-bold inline-flex items-center transition"
                                    >
                                        {{ __('View') }}
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center space-y-2 text-slate-400">
                                <i class="fa-solid fa-calendar-check text-2xl text-slate-300 dark:text-zinc-700"></i>
                                <p class="text-xs">{{ __('No departures scheduled for the next 7 days.') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Card: Recent Booking Activity Table -->
                <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs p-5 sm:p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2.5">
                            <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 text-xs">
                                <i class="fa-solid fa-receipt"></i>
                            </span>
                            <div>
                                <h3 class="font-bold text-base text-slate-900 dark:text-white">
                                    {{ __('Recent Booking Activity') }}
                                </h3>
                                <p class="text-xs text-slate-400">
                                    {{ __('Latest direct guest reservations placed on your storefront.') }}
                                </p>
                            </div>
                        </div>

                        <a href="{{ route('reservations.index') }}" wire:navigate class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                            <span>{{ __('All Bookings') }}</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>

                    <!-- Mobile Responsive Card List (md:hidden) -->
                    <div class="md:hidden space-y-3.5 pt-2">
                        @forelse ($recentBookings as $res)
                            @php
                                $payment = $res->latestPayment;
                                $resCode = $res->code ?? ('RSV-' . strtoupper(substr($res->id, -8)));
                            @endphp
                            <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xs space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono font-extrabold text-xs text-indigo-600 dark:text-indigo-400">
                                        #{{ $resCode }}
                                    </span>
                                    @if ($res->status === \App\Enums\ReservationStatus::Confirmed)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            {{ __('Confirmed') }}
                                        </span>
                                    @elseif ($res->status === \App\Enums\ReservationStatus::PendingConfirmation)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            {{ __('Pending Review') }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-slate-300">
                                            {{ $res->status->label() }}
                                        </span>
                                    @endif
                                </div>

                                <div class="space-y-0.5">
                                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">
                                        {{ $res->guest_name }}
                                    </h4>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        {{ $res->requested_date->format('M d, Y') }} &bull; {{ __(':count Pax', ['count' => $res->pax_count]) }}
                                    </p>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-zinc-800 text-xs">
                                    <span class="text-slate-400 text-[10px] uppercase font-bold">{{ __('Amount') }}</span>
                                    <span class="font-mono font-black text-slate-900 dark:text-white">
                                        {{ $payment && $payment->isPaid() ? 'Rp ' . number_format((float) $payment->amount, 0, ',', '.') : '—' }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-xs text-slate-400">
                                {{ __('No bookings received yet. Share your storefront link to start taking reservations!') }}
                            </div>
                        @endforelse
                    </div>

                    <!-- Desktop Recent Bookings Table (hidden on mobile) -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-zinc-800">
                                <tr>
                                    <th class="pb-2.5">{{ __('Booking Code') }}</th>
                                    <th class="pb-2.5">{{ __('Guest') }}</th>
                                    <th class="pb-2.5">{{ __('Trip Date') }}</th>
                                    <th class="pb-2.5">{{ __('Amount') }}</th>
                                    <th class="pb-2.5 text-right">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                                @forelse ($recentBookings as $res)
                                    @php
                                        $payment = $res->latestPayment;
                                        $resCode = $res->code ?? ('RSV-' . strtoupper(substr($res->id, -8)));
                                    @endphp
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800/40 transition">
                                        <td class="py-3 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                            #{{ $resCode }}
                                        </td>
                                        <td class="py-3 font-bold text-slate-900 dark:text-white">
                                            {{ $res->guest_name }}
                                        </td>
                                        <td class="py-3 text-slate-600 dark:text-slate-300">
                                            {{ $res->requested_date->format('M d, Y') }} ({{ $res->pax_count }}p)
                                        </td>
                                        <td class="py-3 font-bold text-slate-900 dark:text-white">
                                            {{ $payment && $payment->isPaid() ? 'Rp ' . number_format((float) $payment->amount, 0, ',', '.') : '—' }}
                                        </td>
                                        <td class="py-3 text-right">
                                            @if ($res->status === \App\Enums\ReservationStatus::Confirmed)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                    {{ __('Confirmed') }}
                                                </span>
                                            @elseif ($res->status === \App\Enums\ReservationStatus::PendingConfirmation)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 animate-pulse">
                                                    {{ __('Pending Review') }}
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-slate-300">
                                                    {{ $res->status->label() }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-400">
                                            {{ __('No bookings received yet. Share your storefront link to start taking reservations!') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quick Actions, Storefront Sharing, & Setup Checklist (4 cols) -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Quick Actions Card -->
                <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs p-5 sm:p-6 space-y-4">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider text-[11px] text-slate-400">
                        {{ __('Quick Operator Actions') }}
                    </h3>

                    <div class="space-y-2">
                        <a
                            href="{{ route('packages.create') }}"
                            wire:navigate
                            class="p-3 rounded-2xl border border-slate-200 dark:border-zinc-800 hover:border-indigo-300 dark:hover:border-indigo-700 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/30 transition flex items-center justify-between text-xs font-bold text-slate-800 dark:text-slate-200 group"
                        >
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs group-hover:scale-110 transition-transform">
                                    <i class="fa-solid fa-plus"></i>
                                </span>
                                <span>{{ __('Create Tour Package') }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 group-hover:translate-x-0.5 transition-transform"></i>
                        </a>

                        <a
                            href="{{ route('products.create') }}"
                            wire:navigate
                            class="p-3 rounded-2xl border border-slate-200 dark:border-zinc-800 hover:border-sky-300 dark:hover:border-sky-700 hover:bg-sky-50/50 dark:hover:bg-sky-950/30 transition flex items-center justify-between text-xs font-bold text-slate-800 dark:text-slate-200 group"
                        >
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-950/80 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xs group-hover:scale-110 transition-transform">
                                    <i class="fa-solid fa-box-open"></i>
                                </span>
                                <span>{{ __('Add Activity / Resource') }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 group-hover:translate-x-0.5 transition-transform"></i>
                        </a>

                        <a
                            href="{{ route('calendar.index') }}"
                            wire:navigate
                            class="p-3 rounded-2xl border border-slate-200 dark:border-zinc-800 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/30 transition flex items-center justify-between text-xs font-bold text-slate-800 dark:text-slate-200 group"
                        >
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/80 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs group-hover:scale-110 transition-transform">
                                    <i class="fa-solid fa-calendar-days"></i>
                                </span>
                                <span>{{ __('Availability & Blackout Dates') }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 group-hover:translate-x-0.5 transition-transform"></i>
                        </a>

                        <a
                            href="{{ route('guests.index') }}"
                            wire:navigate
                            class="p-3 rounded-2xl border border-slate-200 dark:border-zinc-800 hover:border-purple-300 dark:hover:border-purple-700 hover:bg-purple-50/50 dark:hover:bg-purple-950/30 transition flex items-center justify-between text-xs font-bold text-slate-800 dark:text-slate-200 group"
                        >
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/80 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xs group-hover:scale-110 transition-transform">
                                    <i class="fa-solid fa-address-book"></i>
                                </span>
                                <span>{{ __('Guest Directory & CRM') }}</span>
                            </div>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 group-hover:translate-x-0.5 transition-transform"></i>
                        </a>
                    </div>
                </div>

                <!-- Storefront Share Card -->
                @if ($operator)
                    <div class="rounded-3xl bg-gradient-to-br from-indigo-600 to-indigo-800 p-5 sm:p-6 text-white shadow-lg space-y-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-share-nodes text-indigo-200 text-sm"></i>
                            <h4 class="font-extrabold text-sm">{{ __('Share Direct Storefront') }}</h4>
                        </div>
                        <p class="text-xs text-indigo-100">
                            {{ __('Share your verified link on Instagram bio, WhatsApp, or Google Business to take direct commissions-free bookings.') }}
                        </p>
                        <div class="p-2.5 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-between gap-2">
                            <span class="font-mono text-xs text-indigo-200 truncate select-all">
                                {{ $storefrontUrl }}
                            </span>
                            <button
                                type="button"
                                x-on:click="navigator.clipboard.writeText('{{ $storefrontUrl }}'); copied = true; setTimeout(() => copied = false, 2500)"
                                class="px-2.5 py-1 rounded-xl bg-white text-indigo-700 text-xs font-bold shrink-0 hover:bg-indigo-50 transition cursor-pointer shadow-xs"
                            >
                                <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Storefront Setup Checklist (if incomplete) -->
                @if ($completedSteps < 4)
                    <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs p-5 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                {{ __('Setup Checklist') }}
                            </span>
                            <span class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400">
                                {{ $completedSteps }}/4 ({{ round($progressPercent) }}%)
                            </span>
                        </div>

                        <!-- Mini Progress -->
                        <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-zinc-800 overflow-hidden">
                            <div class="h-full bg-indigo-600 rounded-full" style="width: {{ $progressPercent }}%;"></div>
                        </div>

                        <div class="space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="{{ $hasProfile ? 'text-slate-400 line-through' : 'font-semibold text-slate-800 dark:text-slate-200' }}">{{ __('1. Bio & WhatsApp') }}</span>
                                <i class="fa-solid {{ $hasProfile ? 'fa-check text-emerald-500' : 'fa-circle-dot text-slate-300' }}"></i>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="{{ $hasTerms ? 'text-slate-400 line-through' : 'font-semibold text-slate-800 dark:text-slate-200' }}">{{ __('2. Cancellation Policies') }}</span>
                                <i class="fa-solid {{ $hasTerms ? 'fa-check text-emerald-500' : 'fa-circle-dot text-slate-300' }}"></i>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="{{ $hasBank ? 'text-slate-400 line-through' : 'font-semibold text-slate-800 dark:text-slate-200' }}">{{ __('3. Payout Bank Account') }}</span>
                                <i class="fa-solid {{ $hasBank ? 'fa-check text-emerald-500' : 'fa-circle-dot text-slate-300' }}"></i>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="{{ ($hasProducts && $hasPackages) ? 'text-slate-400 line-through' : 'font-semibold text-slate-800 dark:text-slate-200' }}">{{ __('4. Packages & Products') }}</span>
                                <i class="fa-solid {{ ($hasProducts && $hasPackages) ? 'fa-check text-emerald-500' : 'fa-circle-dot text-slate-300' }}"></i>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts::app>
