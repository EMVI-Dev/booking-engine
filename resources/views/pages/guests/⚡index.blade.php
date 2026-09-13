<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Guest CRM')] class extends Component {
    use WithPagination;
    use ResolvesCurrentOperator;

    public string $search = '';

    public string $filter = 'all'; // all, repeat, vip, with_notes

    public string $sortBy = 'recent'; // recent, spent, bookings, name, last_trip, next_trip

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{
     *     total_unique_guests: int,
     *     repeat_guests_count: int,
     *     repeat_rate: float,
     *     total_pax: int,
     *     total_revenue: float,
     *     avg_guest_value: float
     * }
     */
    #[Computed]
    public function metrics(): array
    {
        if (! $this->currentOperator) {
            return [
                'total_unique_guests' => 0,
                'repeat_guests_count' => 0,
                'repeat_rate' => 0.0,
                'total_pax' => 0,
                'total_revenue' => 0.0,
                'avg_guest_value' => 0.0,
            ];
        }

        $guests = $this->currentOperator
            ->guests()
            ->with(['reservations.payments'])
            ->withCount('reservations')
            ->get();

        $totalUnique = $guests->count();

        if ($totalUnique === 0) {
            return [
                'total_unique_guests' => 0,
                'repeat_guests_count' => 0,
                'repeat_rate' => 0.0,
                'total_pax' => 0,
                'total_revenue' => 0.0,
                'avg_guest_value' => 0.0,
            ];
        }

        $repeatCount = $guests->filter(fn (Guest $g) => $g->reservations_count > 1)->count();
        $totalPax = (int) $guests->sum(fn (Guest $g) => $g->total_pax);
        $totalRevenue = (float) $guests->sum(fn (Guest $g) => $g->total_spent);
        $repeatRate = round(($repeatCount / $totalUnique) * 100, 1);
        $avgValue = round($totalRevenue / $totalUnique, 0);

        return [
            'total_unique_guests' => $totalUnique,
            'repeat_guests_count' => $repeatCount,
            'repeat_rate' => $repeatRate,
            'total_pax' => $totalPax,
            'total_revenue' => $totalRevenue,
            'avg_guest_value' => $avgValue,
        ];
    }

    /**
     * Guest ids that share email or normalized phone with another guest.
     *
     * @return array<string, true>
     */
    #[Computed]
    public function duplicateGuestIds(): array
    {
        if (! $this->currentOperator) {
            return [];
        }

        $guests = $this->currentOperator->guests()->get(['id', 'email', 'phone']);
        $byEmail = [];
        $byPhone = [];

        foreach ($guests as $guest) {
            $email = filled($guest->email) ? strtolower(trim((string) $guest->email)) : null;
            if ($email !== null) {
                $byEmail[$email][] = $guest->id;
            }

            $phone = Guest::normalizePhone($guest->phone);
            if ($phone !== '') {
                $byPhone[$phone][] = $guest->id;
            }
        }

        $ids = [];
        foreach (array_merge(array_values($byEmail), array_values($byPhone)) as $group) {
            if (count($group) < 2) {
                continue;
            }
            foreach ($group as $id) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    protected function filteredGuestsQuery(): Builder
    {
        $query = $this->currentOperator
            ->guests()
            ->getQuery()
            ->with(['reservations.bookable', 'reservations.payments'])
            ->withCount('reservations')
            ->withMax('reservations as last_trip_at', 'requested_date')
            ->withMin([
                'reservations as next_trip_at' => fn (Builder $q) => $q
                    ->whereDate('requested_date', '>=', now()->toDateString())
                    ->whereIn('status', [
                        ReservationStatus::PaymentPending->value,
                        ReservationStatus::PendingConfirmation->value,
                        ReservationStatus::Confirmed->value,
                    ]),
            ], 'requested_date');

        $spentSubquery = DB::table('payments')
            ->join('reservations', 'reservations.id', '=', 'payments.reservation_id')
            ->whereColumn('reservations.guest_id', 'guests.id')
            ->where('payments.status', PaymentStatus::Paid->value)
            ->selectRaw('coalesce(sum(payments.amount), 0)');

        $query->selectSub($spentSubquery, 'lifetime_spent');

        if (trim($this->search) !== '') {
            $search = '%'.trim($this->search).'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search)
                    ->orWhere('notes', 'like', $search);
            });
        }

        if ($this->filter === 'repeat') {
            $query->has('reservations', '>', 1);
        } elseif ($this->filter === 'vip') {
            $query->where(function (Builder $q): void {
                $q->whereJsonContains('tags', 'VIP')->orWhereJsonContains('tags', 'vip');
            });
        } elseif ($this->filter === 'with_notes') {
            $query->whereNotNull('notes')->where('notes', '!=', '');
        }

        return match ($this->sortBy) {
            'name' => $query->orderBy('name', 'asc'),
            'bookings' => $query->orderByDesc('reservations_count'),
            'spent' => $query->orderByDesc('lifetime_spent'),
            'last_trip' => $query->orderByDesc('last_trip_at'),
            'next_trip' => $query->orderByRaw('next_trip_at is null')->orderBy('next_trip_at'),
            default => $query->latest('updated_at'),
        };
    }

    #[Computed]
    public function guests(): LengthAwarePaginator
    {
        if (! $this->currentOperator) {
            return new LengthAwarePaginator([], 0, 15);
        }

        return $this->filteredGuestsQuery()->paginate(15);
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless($this->currentOperator?->hasFeature('guest_crm') ?? false, 403);

        $filename = 'guests-'.now()->format('Y-m-d').'.csv';
        $rows = $this->filteredGuestsQuery()->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Name',
                'Email',
                'Phone',
                'Tags',
                'Bookings',
                'Lifetime spend',
                'Last trip',
                'Next trip',
                'Notes',
            ]);

            foreach ($rows as $guest) {
                fputcsv($out, [
                    $guest->name,
                    $guest->email,
                    $guest->phone,
                    is_array($guest->tags) ? implode('; ', $guest->tags) : '',
                    $guest->reservations_count,
                    number_format((float) ($guest->lifetime_spent ?? $guest->total_spent), 0, '.', ''),
                    $guest->last_trip_at ? \Illuminate\Support\Carbon::parse($guest->last_trip_at)->toDateString() : '',
                    $guest->next_trip_at ? \Illuminate\Support\Carbon::parse($guest->next_trip_at)->toDateString() : '',
                    $guest->notes,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}; ?>

<div class="space-y-6">
    @if (!$this->currentOperator?->hasFeature('guest_crm'))
        <div class="py-6">
            <x-feature-gate :title="__('Guest CRM')" :description="__(
                'Unlock guest profiles, VIP tags, repeat booking history, and WhatsApp follow-ups.',
            )" required-plan="Growth" plan-slug="growth"
                icon="fa-solid fa-address-book" :features="[
                    __('Guest profiles with email and WhatsApp contacts'),
                    __('Lifetime spend and completed trip counts'),
                    __('Notes, tags, and VIP categorization'),
                    __('One-click WhatsApp messaging for rebooking'),
                ]" />
        </div>
    @else
        <x-page-header
            :title="__('Guest CRM')"
            :subtitle="__('Scan who is next, then open a profile for history and actions.')"
            icon="fa-address-book"
        >
            <x-slot:actions>
                <x-button type="button" wire:click="exportCsv" variant="secondary">
                    <i class="fa-solid fa-file-csv text-xs"></i>
                    <span>{{ __('Export CSV') }}</span>
                </x-button>
                <x-button :href="route('reservations.index')" variant="secondary" wire:navigate>
                    <i class="fa-solid fa-calendar-check text-xs"></i>
                    <span>{{ __('All Bookings') }}</span>
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <x-metric-card
                :label="__('Unique Guests')"
                :value="number_format($this->metrics['total_unique_guests'])"
                :hint="__('Dedicated customer profiles')"
                icon="fa-users"
                tone="brand"
            />
            <x-metric-card
                :label="__('Repeat Customers')"
                :value="number_format($this->metrics['repeat_guests_count'])"
                :suffix="'('.$this->metrics['repeat_rate'].'%)'"
                :hint="__('Customers with 2+ bookings')"
                icon="fa-repeat"
                tone="success"
            />
            <x-metric-card
                :label="__('Total Passengers')"
                :value="number_format($this->metrics['total_pax'])"
                :hint="__('Lifetime passenger headcount')"
                icon="fa-person-walking-luggage"
                tone="info"
            />
            <x-metric-card
                :label="__('Avg. Guest Value')"
                :value="'Rp '.number_format($this->metrics['avg_guest_value'], 0, ',', '.')"
                :hint="__('Total: Rp :amount', ['amount' => number_format($this->metrics['total_revenue'], 0, ',', '.')])"
                icon="fa-rupiah-sign"
                tone="info"
            />
        </div>

        <x-toolbar>
            <div class="flex flex-col items-stretch justify-between gap-3 md:flex-row md:items-center">
                <div class="relative flex-1">
                    <x-search-input
                        wire:model.live.debounce.300ms="search"
                        :placeholder="__('Search by guest name, email, WhatsApp, or CRM notes...')"
                    />
                    @if ($search !== '')
                        <button wire:click="$set('search', '')"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-op-subtle hover:text-op-ink">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    @endif
                </div>
                <div class="w-full sm:w-56">
                    <x-select wire:model.live="sortBy" :options="[
                        'recent' => __('Recently Active'),
                        'spent' => __('Highest Lifetime Spend'),
                        'bookings' => __('Most Bookings Count'),
                        'next_trip' => __('Next Trip Soonest'),
                        'last_trip' => __('Last Trip Newest'),
                        'name' => __('Guest Name (A-Z)'),
                    ]" />
                </div>
            </div>

            <x-filter-tabs class="border-t border-op-line pt-3">
                @php
                    $filterTabs = [
                        'all' => __('All Guests (:count)', ['count' => $this->metrics['total_unique_guests']]),
                        'repeat' => __('Repeat Customers (:count)', ['count' => $this->metrics['repeat_guests_count']]),
                        'vip' => __('VIP Tagged'),
                        'with_notes' => __('With CRM Notes'),
                    ];
                @endphp

                @foreach ($filterTabs as $tabKey => $tabLabel)
                    <x-filter-tab :active="$filter === $tabKey" wire:click="$set('filter', '{{ $tabKey }}')">
                        {{ $tabLabel }}
                    </x-filter-tab>
                @endforeach
            </x-filter-tabs>
        </x-toolbar>

        <div
            class="rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs overflow-hidden">
            <div class="md:hidden space-y-3 p-3" wire:loading.class="opacity-60">
                @forelse ($this->guests as $guest)
                    @php
                        $initials = strtoupper(substr($guest->name, 0, 2));
                        $totalSpent = (float) ($guest->lifetime_spent ?? $guest->total_spent);
                        $isRepeat = $guest->reservations_count > 1;
                        $isDuplicate = isset($this->duplicateGuestIds[$guest->id]);
                        $lastTrip = $guest->last_trip_at ? \Illuminate\Support\Carbon::parse($guest->last_trip_at) : null;
                        $nextTrip = $guest->next_trip_at ? \Illuminate\Support\Carbon::parse($guest->next_trip_at) : null;
                    @endphp
                    <div
                        class="p-4 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xs space-y-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div
                                class="w-8 h-8 rounded-xl bg-[#FFEF4D] text-[#090d16] font-black text-xs flex items-center justify-center shrink-0">
                                {{ $initials }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <span class="font-extrabold text-xs text-slate-900 dark:text-white block truncate">
                                    {{ $guest->name }}
                                </span>
                                <div class="flex flex-wrap gap-1 mt-0.5">
                                    @if ($isRepeat)
                                        <span
                                            class="px-1.5 py-0.2 rounded-full text-[9px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60">
                                            {{ __('Repeat') }}
                                        </span>
                                    @endif
                                    @if ($isDuplicate)
                                        <span
                                            class="px-1.5 py-0.2 rounded-full text-[9px] font-black uppercase bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-800/60">
                                            {{ __('Duplicate?') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <a href="{{ route('guests.show', $guest) }}" wire:navigate
                                class="h-8 w-8 rounded-xl inline-flex items-center justify-center bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-[#1e2433] text-xs transition cursor-pointer shrink-0"
                                title="{{ __('View guest') }}"
                                aria-label="{{ __('View guest') }}">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-[11px] pt-2 border-t border-slate-100 dark:border-[#1e2433]">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Last trip') }}</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $lastTrip?->format('M j, Y') ?? '—' }}
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">{{ __('Next trip') }}</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $nextTrip?->format('M j, Y') ?? '—' }}
                                </span>
                            </div>
                        </div>
                        <p class="font-mono font-black text-xs text-slate-900 dark:text-white">
                            Rp {{ number_format($totalSpent, 0, ',', '.') }}
                        </p>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-slate-400">
                        {{ __('No guests found') }}
                    </div>
                @endforelse
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead
                        class="bg-slate-50 dark:bg-[#10141d] border-b border-slate-200/80 dark:border-[#1e2433] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5">{{ __('Guest') }}</th>
                            <th class="px-4 py-3.5">{{ __('Contact') }}</th>
                            <th class="px-4 py-3.5 text-center">{{ __('Bookings') }}</th>
                            <th class="px-4 py-3.5">{{ __('Lifetime Spend') }}</th>
                            <th class="px-4 py-3.5">{{ __('Last trip') }}</th>
                            <th class="px-4 py-3.5">{{ __('Next trip') }}</th>
                            <th class="px-5 py-3.5">{{ __('Notes') }}</th>
                            <th class="px-4 py-3.5 text-right"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                        @forelse ($this->guests as $guest)
                            @php
                                $initials = strtoupper(substr($guest->name, 0, 2));
                                $totalSpent = (float) ($guest->lifetime_spent ?? $guest->total_spent);
                                $isRepeat = $guest->reservations_count > 1;
                                $isDuplicate = isset($this->duplicateGuestIds[$guest->id]);
                                $lastTrip = $guest->last_trip_at ? \Illuminate\Support\Carbon::parse($guest->last_trip_at) : null;
                                $nextTrip = $guest->next_trip_at ? \Illuminate\Support\Carbon::parse($guest->next_trip_at) : null;
                            @endphp
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-9 h-9 rounded-2xl bg-[#FFEF4D] text-[#090d16] font-black text-xs flex items-center justify-center shrink-0 shadow-xs">
                                            {{ $initials }}
                                        </div>
                                        <div class="space-y-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-bold text-slate-900 dark:text-white truncate">
                                                    {{ $guest->name }}
                                                </span>
                                                @if ($isRepeat)
                                                    <span
                                                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800/60 shrink-0">
                                                        {{ __('Repeat') }}
                                                    </span>
                                                @endif
                                                @if ($isDuplicate)
                                                    <span
                                                        class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-800/60 shrink-0">
                                                        {{ __('Duplicate?') }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if (is_array($guest->tags) && count($guest->tags) > 0)
                                                <div class="flex flex-wrap items-center gap-1">
                                                    @foreach ($guest->tags as $tag)
                                                        <span
                                                            class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-[#141821] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]">
                                                            {{ $tag }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-1 text-xs text-slate-700 dark:text-slate-300">
                                        <p class="truncate max-w-[200px]">{{ $guest->email ?: __('No email') }}</p>
                                        <p class="font-semibold {{ $guest->phone ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-400' }}">
                                            {{ $guest->phone ?: __('No phone') }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center whitespace-nowrap">
                                    <span class="font-extrabold text-xs text-slate-900 dark:text-white">
                                        {{ $guest->reservations_count }}
                                    </span>
                                    <p class="text-[11px] text-slate-500">
                                        {{ __(':count pax', ['count' => $guest->total_pax]) }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <p class="font-bold text-xs text-slate-900 dark:text-white">
                                        Rp {{ number_format($totalSpent, 0, ',', '.') }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $lastTrip?->format('M j, Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $nextTrip?->format('M j, Y') ?? '—' }}
                                </td>
                                <td class="px-5 py-4 max-w-[180px] text-xs text-slate-600 dark:text-slate-300">
                                    @if ($guest->notes)
                                        <span class="line-clamp-2" title="{{ $guest->notes }}">{{ $guest->notes }}</span>
                                    @else
                                        <span class="text-slate-400 italic">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right whitespace-nowrap">
                                    <a href="{{ route('guests.show', $guest) }}" wire:navigate
                                        class="h-8 w-8 rounded-xl inline-flex items-center justify-center bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-[#1e2433] text-xs transition cursor-pointer"
                                        title="{{ __('View guest') }}"
                                        aria-label="{{ __('View guest') }}">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-12 text-center text-slate-400 text-xs">
                                    {{ __('No guests found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->guests->hasPages())
                <div class="border-t border-slate-100 px-4 py-3 dark:border-[#1e2433]">
                    {{ $this->guests->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
