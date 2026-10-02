<?php

use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\PlatformCoupon;
use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app.sidebar')] #[Title('Coupon Performance Report - Operator Portal')] class extends Component {
    use WithPagination;

    public PlatformCoupon $coupon;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $sort = 'latest';

    public int $perPage = 15;

    public function mount(PlatformCoupon $coupon): void
    {
        $operator = $this->operator;

        if (! $operator || $coupon->operator_id !== $operator->id || $coupon->scope !== 'guest') {
            abort(404);
        }

        $this->coupon = $coupon;
    }

    public function getOperatorProperty(): ?Operator
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user?->currentOperator();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    /**
     * Base query for all reservations redeemed under this coupon.
     */
    protected function baseRedemptionsQuery()
    {
        return Reservation::query()
            ->where('operator_id', $this->operator?->id ?? 'none')
            ->where(function ($q): void {
                $q->where('terms_snapshot->coupon_code', $this->coupon->code)
                    ->orWhere('terms_snapshot->coupon_code', strtoupper($this->coupon->code));
            });
    }

    /**
     * Compute aggregate metrics across all historical redemptions.
     *
     * @return array{
     *     total_uses: int,
     *     max_uses: int|null,
     *     total_discounts: float,
     *     gross_volume: float,
     *     net_revenue: float,
     *     aov: float,
     *     avg_discount: float,
     *     unique_guests: int
     * }
     */
    #[Computed]
    public function metrics(): array
    {
        $all = $this->baseRedemptionsQuery()->with('latestPayment')->get();

        $totalUses = max($this->coupon->used_count, $all->count());
        $totalDiscounts = (float) $all->sum(fn ($r) => (float) ($r->terms_snapshot['discount_amount'] ?? 0));
        $grossVolume = (float) $all->sum(fn ($r) => (float) ($r->terms_snapshot['subtotal'] ?? 0));
        $netRevenue = (float) $all->sum(fn ($r) => $r->getChargedAmount());
        $bookingCount = $all->count();
        $aov = $bookingCount > 0 ? ($grossVolume / $bookingCount) : 0.0;
        $avgDiscount = $bookingCount > 0 ? ($totalDiscounts / $bookingCount) : 0.0;
        $uniqueGuests = $all->pluck('guest_email')->filter()->unique()->count();

        return [
            'total_uses' => $totalUses,
            'max_uses' => $this->coupon->max_uses,
            'total_discounts' => $totalDiscounts,
            'gross_volume' => $grossVolume,
            'net_revenue' => $netRevenue,
            'aov' => $aov,
            'avg_discount' => $avgDiscount,
            'unique_guests' => $uniqueGuests,
        ];
    }

    /**
     * Paginated and filtered redemptions for table display.
     */
    #[Computed]
    public function redemptions(): LengthAwarePaginator
    {
        $query = $this->baseRedemptionsQuery()->with(['bookable', 'latestPayment']);

        if (! empty($this->search)) {
            $s = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($s): void {
                $q->where('code', 'like', $s)
                    ->orWhere('guest_name', 'like', $s)
                    ->orWhere('guest_email', 'like', $s)
                    ->orWhere('guest_contact', 'like', $s);
            });
        }

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        match ($this->sort) {
            'oldest' => $query->oldest('created_at'),
            'discount_high' => $query->orderByDesc('terms_snapshot->discount_amount'),
            'gross_high' => $query->orderByDesc('terms_snapshot->subtotal'),
            default => $query->latest('created_at'),
        };

        return $query->paginate($this->perPage);
    }

    /**
     * Stream CSV download of all redemptions for this coupon.
     */
    public function exportCsv()
    {
        $filename = 'coupon-report-' . strtolower($this->coupon->code) . '-' . now()->format('Y-m-d') . '.csv';
        $rows = $this->baseRedemptionsQuery()
            ->with(['bookable', 'latestPayment'])
            ->latest('created_at')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Booking Reference',
                'Date Booked',
                'Experience Date',
                'Guest Name',
                'Guest Email',
                'Guest Phone',
                'Experience / Package',
                'Pax',
                'Subtotal (Gross IDR)',
                'Discount Amount (IDR)',
                'Net Paid (IDR)',
                'Reservation Status',
            ]);

            foreach ($rows as $r) {
                $snap = $r->terms_snapshot ?? [];
                $discount = (float) ($snap['discount_amount'] ?? 0);
                $subtotal = (float) ($snap['subtotal'] ?? 0);
                $net = $r->getChargedAmount();

                fputcsv($out, [
                    $r->code,
                    $r->created_at?->format('Y-m-d H:i:s') ?? '',
                    $r->requested_date?->format('Y-m-d') ?? '',
                    $r->guest_name,
                    $r->guest_email ?? '',
                    $r->guest_contact ?? '',
                    $r->bookable?->name ?? ($r->bookable?->title ?? 'Direct Booking'),
                    $r->pax_count,
                    number_format($subtotal, 0, '.', ''),
                    number_format($discount, 0, '.', ''),
                    number_format($net, 0, '.', ''),
                    $r->status instanceof ReservationStatus ? $r->status->label() : (string) $r->status,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}; ?>

<div class="space-y-6">
    @php
        $isExpired = $coupon->expires_at && $coupon->expires_at->isPast();
        $isFuture = $coupon->starts_at && $coupon->starts_at->isFuture();
        $isLimitReached = $coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses;
    @endphp

    <!-- Top Navigation & Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-3 min-w-0">
            <x-back-link :href="route('coupons.index')">
                {{ __('All Promo Codes') }}
            </x-back-link>

            <div class="flex items-start gap-3.5 min-w-0">
                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-[#FFEF4D] font-mono font-bold text-lg flex items-center justify-center shrink-0 border border-amber-500/20">
                    <i class="fa-solid fa-ticket"></i>
                </div>

                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white font-mono break-all">
                            {{ $coupon->code }}
                        </h1>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 dark:bg-[#141821] dark:text-slate-200 border border-slate-200 dark:border-[#1e2433]">
                            @if ($coupon->discount_type === 'percentage')
                                {{ (float) $coupon->discount_value }}% {{ __('OFF') }}
                            @else
                                Rp {{ number_format((float) $coupon->discount_value, 0, ',', '.') }} {{ __('OFF') }}
                            @endif
                        </span>
                        @if ($isExpired)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border border-rose-200 dark:border-rose-900/50">
                                {{ __('Expired') }}
                            </span>
                        @elseif ($isLimitReached)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-900/50">
                                {{ __('Limit Reached') }}
                            </span>
                        @elseif (! $coupon->is_active)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300 border border-slate-200 dark:border-zinc-700">
                                {{ __('Inactive') }}
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/50">
                                {{ __('Active on Storefront') }}
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-x-3 gap-y-1">
                        @if ($coupon->description)
                            <span>{{ $coupon->description }}</span>
                            <span>&bull;</span>
                        @endif
                        <span>{{ __('Min spend: :val', ['val' => $coupon->min_spend > 0 ? 'Rp ' . number_format((float) $coupon->min_spend, 0, ',', '.') : __('None')]) }}</span>
                        @if ($coupon->max_discount_amount)
                            <span>&bull;</span>
                            <span>{{ __('Cap: Rp :val', ['val' => number_format((float) $coupon->max_discount_amount, 0, ',', '.')]) }}</span>
                        @endif
                        <span>&bull;</span>
                        <span>{{ $coupon->expires_at ? __('Expires :date', ['date' => $coupon->expires_at->format('d M Y')]) : __('Never expires') }}</span>
                        <span>&bull;</span>
                        <span>{{ __('Quota: :used / :max', ['used' => $coupon->used_count, 'max' => $coupon->max_uses ?? '∞']) }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto">
            <x-button
                type="button"
                wire:click="exportCsv"
                wire:loading.attr="disabled"
                variant="secondary"
                size="sm"
                class="w-full sm:w-auto justify-center"
            >
                <span wire:loading.remove wire:target="exportCsv">
                    <i class="fa-solid fa-file-arrow-down mr-1.5 text-xs"></i>
                </span>
                <span wire:loading wire:target="exportCsv">
                    <i class="fa-solid fa-circle-notch fa-spin mr-1.5 text-xs"></i>
                </span>
                <span>{{ __('Export CSV') }}</span>
            </x-button>
        </div>
    </div>

    <!-- 5 Standard KPI Cards (2 cols mobile, 5 cols desktop) -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2.5 sm:gap-4">
        <x-metric-card
            :label="__('Total Redemptions')"
            :value="$this->metrics['total_uses']"
            :hint="__(':count unique guests', ['count' => $this->metrics['unique_guests']])"
            icon="fa-ticket"
            tone="neutral"
        />
        <x-metric-card
            :label="__('Discounts Given')"
            :value="'Rp ' . number_format($this->metrics['total_discounts'], 0, ',', '.')"
            :hint="__('Avg Rp :avg/booking', ['avg' => number_format($this->metrics['avg_discount'], 0, ',', '.')])"
            icon="fa-tag"
            tone="warning"
        />
        <x-metric-card
            :label="__('Gross Volume')"
            :value="'Rp ' . number_format($this->metrics['gross_volume'], 0, ',', '.')"
            :hint="__('Subtotal before discount')"
            icon="fa-money-bill-wave"
            tone="neutral"
        />
        <x-metric-card
            :label="__('Net Revenue')"
            :value="'Rp ' . number_format($this->metrics['net_revenue'], 0, ',', '.')"
            :hint="__('Collected net proceeds')"
            icon="fa-vault"
            tone="success"
        />
        <x-metric-card
            class="col-span-2 lg:col-span-1"
            :label="__('Average Order Value')"
            :value="'Rp ' . number_format($this->metrics['aov'], 0, ',', '.')"
            :hint="__('AOV per redemption')"
            icon="fa-chart-line"
            tone="info"
        />
    </div>

    <!-- Toolbar Filters -->
    <x-toolbar>
        <div class="flex flex-col items-stretch justify-between gap-3 md:flex-row md:items-center">
            <div class="relative flex-1">
                <x-search-input
                    wire:model.live.debounce.300ms="search"
                    :placeholder="__('Search by ref, guest, or email...')"
                />
                @if ($search !== '')
                    <button wire:click="$set('search', '')"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-op-subtle hover:text-op-ink">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                @endif
            </div>

            <div class="w-full sm:w-56">
                <x-select
                    wire:model.live="sort"
                    :options="[
                        'latest' => __('Newest First'),
                        'oldest' => __('Oldest First'),
                        'discount_high' => __('Highest Discount'),
                        'gross_high' => __('Highest Booking Value'),
                    ]"
                />
            </div>
        </div>

        <x-filter-tabs class="border-t border-op-line pt-3">
            @foreach ([
                'all' => __('All Statuses'),
                'confirmed' => __('Confirmed'),
                'completed' => __('Completed'),
                'payment_pending' => __('Pending Payment'),
                'cancelled' => __('Cancelled'),
            ] as $st => $label)
                <x-filter-tab :active="$status === $st" wire:click="$set('status', '{{ $st }}')">
                    {{ $label }}
                </x-filter-tab>
            @endforeach
        </x-filter-tabs>
    </x-toolbar>

    <!-- Itemized Redemptions Table & Mobile Card List -->
    <div class="rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-[#1e2433] flex items-center justify-between gap-4 bg-slate-50/50 dark:bg-[#10141d]/50">
            <div>
                <h3 class="text-sm font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __('Itemized Redemption Transactions') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Every booking checkout where code :code was redeemed.', ['code' => $coupon->code]) }}
                </p>
            </div>
            <span class="text-xs font-mono font-bold text-slate-500 dark:text-slate-400">
                {{ $this->redemptions->total() }} {{ __('records') }}
            </span>
        </div>

        <!-- Mobile Responsive Card List (md:hidden) -->
        <div class="md:hidden space-y-3 p-3 transition-opacity duration-200" wire:loading.class="opacity-60">
            @forelse ($this->redemptions as $res)
                @php
                    $snap = $res->terms_snapshot ?? [];
                    $discount = (float) ($snap['discount_amount'] ?? 0);
                    $subtotal = (float) ($snap['subtotal'] ?? 0);
                    $net = $res->getChargedAmount();
                    $waUrl = \App\Services\PhoneNumber::whatsAppLink($res->guest_contact);
                @endphp
                <div class="p-4 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] space-y-3 shadow-2xs">
                    <!-- Top row: Ref + Status -->
                    <div class="flex items-center justify-between gap-2">
                        <a href="{{ route('reservations.show', $res->code) }}" wire:navigate
                            class="font-mono font-bold text-xs text-slate-900 dark:text-[#FFEF4D] hover:underline inline-flex items-center gap-1">
                            <span>#{{ $res->code }}</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px] opacity-70"></i>
                        </a>
                        <x-status-badge :status="$res->status" />
                    </div>

                    <!-- Middle: Guest & Experience -->
                    <div class="space-y-1 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-slate-900 dark:text-white truncate">
                                {{ $res->guest_name }}
                            </span>
                            @if ($res->guest_contact && $waUrl)
                                <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="text-emerald-600 hover:text-emerald-700 text-xs shrink-0 flex items-center gap-1" title="{{ __('WhatsApp') }}">
                                    <i class="fa-brands fa-whatsapp text-sm"></i>
                                    <span class="text-[10px] font-semibold">{{ __('Chat') }}</span>
                                </a>
                            @endif
                        </div>
                        <div class="text-slate-500 dark:text-slate-400 text-[11px] truncate">
                            {{ $res->bookable?->name ?? ($res->bookable?->title ?? __('Direct Experience')) }}
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-400">
                            <span><i class="fa-solid fa-users text-[9px] mr-1"></i>{{ $res->pax_count }} {{ __('pax') }}</span>
                            <span>&bull;</span>
                            <span><i class="fa-regular fa-calendar text-[9px] mr-1"></i>{{ $res->requested_date?->format('d M Y') }}</span>
                        </div>
                    </div>

                    <!-- Bottom: Pricing & View Button -->
                    <div class="pt-2.5 border-t border-slate-100 dark:border-[#1e2433] flex items-center justify-between gap-2">
                        <div class="space-y-0.5 font-mono">
                            <div class="text-[11px] text-slate-400">
                                <span>{{ __('Subtotal:') }} Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                                @if ($discount > 0)
                                    <span class="text-amber-600 dark:text-amber-400 font-semibold ml-1">-Rp {{ number_format($discount, 0, ',', '.') }}</span>
                                @endif
                            </div>
                            <div class="font-black text-sm text-slate-900 dark:text-white">
                                Rp {{ number_format($net, 0, ',', '.') }}
                            </div>
                        </div>

                        <x-button :href="route('reservations.show', $res->code)" variant="secondary" size="xs" wire:navigate class="shrink-0 font-bold">
                            <span>{{ __('View') }}</span>
                            <i class="fa-solid fa-chevron-right text-[9px] ml-1"></i>
                        </x-button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-slate-400 dark:text-slate-500">
                    <div class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-[#141821] text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-[#1e2433] flex items-center justify-center mx-auto text-base mb-2">
                        <i class="fa-solid fa-ticket"></i>
                    </div>
                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-xs">{{ __('No redemptions found') }}</h4>
                    <p class="text-[11px] text-slate-500 mt-1 max-w-xs mx-auto">
                        @if ($search || $status !== 'all')
                            {{ __('No transactions matched your search or status filter.') }}
                        @else
                            {{ __('No guests have redeemed promo code :code yet.', ['code' => $coupon->code]) }}
                        @endif
                    </p>
                </div>
            @endforelse
        </div>

        <!-- Desktop Table View (hidden md:block) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-[#10141d] border-b border-slate-200/80 dark:border-[#1e2433] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        <th class="py-3.5 px-4 sm:px-6">{{ __('Booking Ref') }}</th>
                        <th class="py-3.5 px-4">{{ __('Guest Details') }}</th>
                        <th class="py-3.5 px-4">{{ __('Experience & Pax') }}</th>
                        <th class="py-3.5 px-4 text-right">{{ __('Subtotal') }}</th>
                        <th class="py-3.5 px-4 text-right">{{ __('Discount') }}</th>
                        <th class="py-3.5 px-4 text-right">{{ __('Net Paid') }}</th>
                        <th class="py-3.5 px-4 text-center">{{ __('Status') }}</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                    @forelse ($this->redemptions as $res)
                        @php
                            $snap = $res->terms_snapshot ?? [];
                            $discount = (float) ($snap['discount_amount'] ?? 0);
                            $subtotal = (float) ($snap['subtotal'] ?? 0);
                            $net = $res->getChargedAmount();
                            $waUrl = \App\Services\PhoneNumber::whatsAppLink($res->guest_contact);
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition group">
                            <!-- Booking Ref -->
                            <td class="py-3.5 px-4 sm:px-6">
                                <div class="space-y-0.5">
                                    <a
                                        href="{{ route('reservations.show', $res->code) }}"
                                        wire:navigate
                                        class="font-mono font-bold text-xs sm:text-sm text-slate-900 dark:text-white hover:text-amber-600 dark:hover:text-[#FFEF4D] transition inline-flex items-center gap-1"
                                    >
                                        <span>#{{ $res->code }}</span>
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px] opacity-70 group-hover:opacity-100"></i>
                                    </a>
                                    <div class="text-[11px] text-slate-400 font-mono">
                                        {{ $res->created_at?->format('d M Y, H:i') }}
                                    </div>
                                </div>
                            </td>

                            <!-- Guest Details -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-0.5">
                                    <div class="font-bold text-slate-900 dark:text-white truncate max-w-[180px]">
                                        {{ $res->guest_name }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-[180px]">
                                        {{ $res->guest_email ?: __('No email') }}
                                    </div>
                                    @if ($res->guest_contact)
                                        <div class="text-[11px] text-slate-400 flex items-center gap-1.5 pt-0.5">
                                            <span>{{ $res->guest_contact }}</span>
                                            @if ($waUrl)
                                                <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="text-emerald-600 hover:text-emerald-700" title="{{ __('Chat on WhatsApp') }}">
                                                    <i class="fa-brands fa-whatsapp text-xs"></i>
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <!-- Experience & Pax -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-0.5">
                                    <div class="font-medium text-slate-800 dark:text-slate-200 truncate max-w-[200px]" title="{{ $res->bookable?->name ?? ($res->bookable?->title ?? __('Direct Experience')) }}">
                                        {{ $res->bookable?->name ?? ($res->bookable?->title ?? __('Direct Experience')) }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
                                        <span><i class="fa-solid fa-users text-[10px] text-slate-400"></i> {{ $res->pax_count }} {{ __('pax') }}</span>
                                        <span>&bull;</span>
                                        <span><i class="fa-regular fa-calendar text-[10px] text-slate-400"></i> {{ $res->requested_date?->format('d M Y') }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Subtotal -->
                            <td class="py-3.5 px-4 text-right font-mono text-slate-600 dark:text-slate-400">
                                Rp {{ number_format($subtotal, 0, ',', '.') }}
                            </td>

                            <!-- Discount Given -->
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-amber-600 dark:text-amber-400">
                                - Rp {{ number_format($discount, 0, ',', '.') }}
                            </td>

                            <!-- Net Paid -->
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                Rp {{ number_format($net, 0, ',', '.') }}
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3.5 px-4 text-center">
                                <x-status-badge :status="$res->status" />
                            </td>

                            <!-- Action -->
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <x-button
                                    :href="route('reservations.show', $res->code)"
                                    variant="secondary"
                                    size="xs"
                                >
                                    <span>{{ __('View') }}</span>
                                    <i class="fa-solid fa-chevron-right text-[9px] ml-1"></i>
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-16 text-center text-slate-400 dark:text-slate-500">
                                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-[#141821] text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-[#1e2433] flex items-center justify-center mx-auto text-xl mb-3">
                                    <i class="fa-solid fa-ticket"></i>
                                </div>
                                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">{{ __('No redemptions found') }}</h4>
                                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                    @if ($search || $status !== 'all')
                                        {{ __('No transactions matched your search or status filter. Try clearing your filters.') }}
                                    @else
                                        {{ __('When guests redeem promo code :code during storefront checkout, their bookings and itemized financial breakdown will appear here.', ['code' => $coupon->code]) }}
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->redemptions->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-[#1e2433] bg-slate-50/50 dark:bg-[#10141d]">
                {{ $this->redemptions->links() }}
            </div>
        @endif
    </div>
</div>
