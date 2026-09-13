<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Guest Profile')] class extends Component {
    use ResolvesCurrentOperator;
    use WithPagination;

    public Guest $guest;

    public string $historyFilter = 'all'; // all, upcoming, past

    public bool $showEditModal = false;

    public string $editName = '';

    public ?string $editEmail = null;

    public ?string $editPhone = null;

    public ?string $editNotes = null;

    public string $editTagsInput = '';

    public function mount(Guest $guest): void
    {
        abort_unless($this->currentOperator?->hasFeature('guest_crm') ?? false, 403);

        if (! $this->currentOperator || $guest->operator_id !== $this->currentOperator->id) {
            abort(404);
        }

        $this->guest = $guest;
    }

    public function updatedHistoryFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function reservations(): LengthAwarePaginator
    {
        $query = $this->guest
            ->reservations()
            ->with(['bookable', 'latestPayment', 'payments'])
            ->latest('requested_date');

        if ($this->historyFilter === 'upcoming') {
            $query->whereDate('requested_date', '>=', now()->toDateString())
                ->whereIn('status', [
                    ReservationStatus::PaymentPending,
                    ReservationStatus::PendingConfirmation,
                    ReservationStatus::Confirmed,
                ]);
        } elseif ($this->historyFilter === 'past') {
            $query->where(function ($q): void {
                $q->whereDate('requested_date', '<', now()->toDateString())
                    ->orWhereIn('status', [
                        ReservationStatus::Completed,
                        ReservationStatus::Cancelled,
                        ReservationStatus::Declined,
                        ReservationStatus::Expired,
                    ]);
            });
        }

        return $query->paginate(10);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Guest>
     */
    #[Computed]
    public function duplicates()
    {
        return $this->guest->possibleDuplicates()->loadCount('reservations');
    }

    public function openEdit(): void
    {
        $this->editName = $this->guest->name;
        $this->editEmail = $this->guest->email;
        $this->editPhone = $this->guest->phone;
        $this->editNotes = $this->guest->notes;
        $this->editTagsInput = is_array($this->guest->tags) ? implode(', ', $this->guest->tags) : '';
        $this->showEditModal = true;
    }

    public function closeEdit(): void
    {
        $this->showEditModal = false;
    }

    public function saveGuest(): void
    {
        abort_unless($this->currentOperator?->hasFeature('guest_crm') ?? false, 403);

        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['nullable', 'email', 'max:255'],
            'editPhone' => ['nullable', 'string', 'max:50'],
            'editNotes' => ['nullable', 'string', 'max:1000'],
            'editTagsInput' => ['nullable', 'string', 'max:255'],
        ]);

        $tags = null;
        if (trim($this->editTagsInput) !== '') {
            $tags = array_values(array_filter(array_map('trim', explode(',', $this->editTagsInput))));
        }

        $this->guest->update([
            'name' => $this->editName,
            'email' => $this->editEmail ? strtolower(trim($this->editEmail)) : null,
            'phone' => $this->editPhone ? trim($this->editPhone) : null,
            'notes' => $this->editNotes ? trim($this->editNotes) : null,
            'tags' => $tags,
        ]);

        $this->guest->refresh();
        $this->showEditModal = false;
        unset($this->duplicates);
        $this->dispatch('toast', message: __('Guest profile saved.'), type: 'success');
    }

    public function mergeDuplicate(string $sourceGuestId): void
    {
        abort_unless($this->currentOperator?->hasFeature('guest_crm') ?? false, 403);

        $source = $this->currentOperator->guests()->findOrFail($sourceGuestId);

        DB::transaction(function () use ($source): void {
            $this->guest->mergeFrom($source);
        });

        $this->guest->refresh();
        unset($this->duplicates);
        unset($this->reservations);
        $this->dispatch('toast', message: __('Guests merged.'), type: 'success');
    }
}; ?>

<div class="space-y-6">
    @php
        $waUrl = $guest->getWhatsAppUrl($this->currentOperator->name ?? '');
        $bookingsUrl = route('reservations.index', array_filter([
            'search' => $guest->email ?: $guest->name,
        ]));
        $initials = strtoupper(substr($guest->name, 0, 2));
        $guest->loadMissing(['reservations.payments']);
        $totalSpent = $guest->total_spent;
        $isRepeat = $guest->reservations()->count() > 1;
    @endphp

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-3 min-w-0">
            <x-back-link :href="route('guests.index')">
                {{ __('Guest CRM') }}
            </x-back-link>

            <div class="flex items-start gap-3.5 min-w-0">
                <div
                    class="w-12 h-12 rounded-2xl bg-[#FFEF4D] text-[#090d16] font-black text-lg flex items-center justify-center shrink-0 shadow-xs">
                    {{ $initials }}
                </div>
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white truncate">
                            {{ $guest->name }}
                        </h1>
                        @if ($isRepeat)
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60">
                                <i class="fa-solid fa-repeat mr-0.5"></i>
                                {{ __('Repeat Guest') }}
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                        @if ($guest->email)
                            <span class="inline-flex items-center gap-1.5">
                                <i class="fa-solid fa-envelope text-[10px]"></i>
                                {{ $guest->email }}
                            </span>
                        @endif
                        @if ($guest->phone)
                            <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-600 dark:text-emerald-400">
                                <i class="fa-brands fa-whatsapp text-[11px]"></i>
                                {{ $guest->phone }}
                            </span>
                        @endif
                    </div>
                    @if (is_array($guest->tags) && count($guest->tags) > 0)
                        <div class="flex flex-wrap gap-1 pt-1">
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
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @if ($guest->phone && $waUrl !== '#')
                <x-button :href="$waUrl" target="_blank" rel="noopener" variant="secondary" size="sm">
                    <i class="fa-brands fa-whatsapp text-xs text-emerald-600"></i>
                    <span>{{ __('Chat') }}</span>
                </x-button>
            @endif
            @if ($guest->email)
                <x-button :href="'mailto:'.$guest->email" variant="secondary" size="sm">
                    <i class="fa-solid fa-envelope text-xs"></i>
                    <span>{{ __('Email') }}</span>
                </x-button>
            @endif
            <x-button type="button" wire:click="openEdit" variant="secondary" size="sm">
                <i class="fa-solid fa-pen text-xs"></i>
                <span>{{ __('Edit') }}</span>
            </x-button>
            <x-button :href="$bookingsUrl" variant="secondary" size="sm" wire:navigate>
                <i class="fa-solid fa-calendar-check text-xs"></i>
                <span>{{ __('Open in Bookings') }}</span>
            </x-button>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-metric-card
            :label="__('Lifetime Spend')"
            :value="'Rp '.number_format($totalSpent, 0, ',', '.')"
            :hint="__('Paid trips only')"
            icon="fa-rupiah-sign"
            tone="info"
        />
        <x-metric-card
            :label="__('Bookings')"
            :value="number_format($guest->reservations()->count())"
            :hint="__('All reservation records')"
            icon="fa-ticket"
            tone="brand"
        />
        <x-metric-card
            :label="__('Total Pax')"
            :value="number_format($guest->total_pax)"
            :hint="__('Lifetime passenger headcount')"
            icon="fa-users"
            tone="info"
        />
        <x-metric-card
            :label="__('Paid Trips')"
            :value="number_format($guest->confirmed_bookings_count)"
            :hint="__('Confirmed or completed')"
            icon="fa-circle-check"
            tone="success"
        />
    </div>

    @if ($this->duplicates->isNotEmpty())
        <div
            class="rounded-2xl border border-amber-300/70 bg-amber-50/80 px-4 py-3 dark:border-amber-800/60 dark:bg-amber-950/40"
            role="status">
            <p class="text-sm font-bold text-amber-900 dark:text-amber-200">
                {{ __('Possible duplicates') }}
            </p>
            <p class="mt-0.5 text-xs text-amber-800/80 dark:text-amber-300/80">
                {{ __('Same email or WhatsApp number. Merge keeps this profile and moves their bookings here.') }}
            </p>
            <ul class="mt-3 space-y-2">
                @foreach ($this->duplicates as $duplicate)
                    <li
                        class="flex flex-col gap-2 rounded-xl border border-amber-200/80 bg-white/70 px-3 py-2 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900/50 dark:bg-[#0C0E13]/60">
                        <div class="min-w-0 text-xs">
                            <p class="font-bold text-slate-900 dark:text-white truncate">{{ $duplicate->name }}</p>
                            <p class="text-slate-500 truncate">
                                {{ $duplicate->email ?: ($duplicate->phone ?: __('No contact')) }}
                                · {{ __(':count bookings', ['count' => $duplicate->reservations_count]) }}
                            </p>
                        </div>
                        <x-button
                            type="button"
                            size="sm"
                            variant="secondary"
                            wire:click="mergeDuplicate('{{ $duplicate->id }}')"
                            wire:confirm="{{ __('Merge this guest into :name? Their bookings move here and the duplicate is removed.', ['name' => $guest->name]) }}"
                        >
                            {{ __('Merge into this guest') }}
                        </x-button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($guest->notes)
        <div
            class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-[#1e2433] dark:bg-[#0C0E13] shadow-xs">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">{{ __('CRM Notes') }}</p>
            <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{{ $guest->notes }}</p>
        </div>
    @endif

    <div
        class="rounded-3xl border border-slate-200/80 bg-white dark:border-[#1e2433] dark:bg-[#0C0E13] shadow-xs overflow-hidden">
        <div
            class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-[#1e2433]">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                {{ __('Trip history') }}
            </h2>
            <x-filter-tabs>
                @foreach (['all' => __('All'), 'upcoming' => __('Upcoming'), 'past' => __('Past')] as $key => $label)
                    <x-filter-tab :active="$historyFilter === $key" wire:click="$set('historyFilter', '{{ $key }}')">
                        {{ $label }}
                    </x-filter-tab>
                @endforeach
            </x-filter-tabs>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-[#1e2433]">
            @forelse ($this->reservations as $res)
                @php
                    $bookable = $res->bookable;
                    $payment = $res->latestPayment;
                    $resCode = $res->code ?? 'RSV-'.strtoupper(substr($res->id, -8));
                @endphp
                <div class="p-4 sm:px-5 space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="font-mono text-[10px] font-bold text-slate-800 dark:text-[#FFEF4D] bg-slate-100 dark:bg-[#FFEF4D]/10 border border-slate-200 dark:border-[#FFEF4D]/30 px-2 py-0.5 rounded-lg">
                                    #{{ $resCode }}
                                </span>
                                <span class="font-bold text-sm text-slate-900 dark:text-white truncate">
                                    {{ $bookable->name ?? ($bookable->title ?? __('Direct Booking')) }}
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                <span class="font-semibold text-slate-700 dark:text-slate-300">
                                    <i class="fa-solid fa-calendar-day text-[10px] mr-1"></i>
                                    {{ $res->requested_date?->format('M d, Y') }}
                                </span>
                                <span>
                                    <i class="fa-solid fa-users text-[10px] mr-1"></i>
                                    {{ __(':count Guests', ['count' => $res->pax_count]) }}
                                </span>
                                @if ($payment?->status === PaymentStatus::Paid)
                                    <span class="font-semibold text-emerald-700 dark:text-emerald-400">
                                        Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <span
                            class="shrink-0 px-2.5 py-0.5 rounded-full text-[11px] font-bold border
                            @if ($res->status === ReservationStatus::Confirmed) bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800/60
                            @elseif ($res->status === ReservationStatus::Completed) bg-slate-100 text-slate-800 border-slate-200 dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700
                            @elseif ($res->status === ReservationStatus::PendingConfirmation) bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800/60
                            @elseif (in_array($res->status, [ReservationStatus::Cancelled, ReservationStatus::Declined, ReservationStatus::Expired], true)) bg-rose-100 text-rose-800 border-rose-300 dark:bg-rose-950 dark:text-rose-300 dark:border-rose-800/60
                            @else bg-slate-100 text-slate-700 border-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700
                            @endif">
                            {{ $res->status->label() }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-xs text-slate-400">
                    {{ __('No trips in this filter.') }}
                </div>
            @endforelse
        </div>

        @if ($this->reservations->hasPages())
            <div class="border-t border-slate-100 px-4 py-3 dark:border-[#1e2433]">
                {{ $this->reservations->links() }}
            </div>
        @endif
    </div>

    @if ($showEditModal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                <div @click.away="$wire.closeEdit()"
                    class="w-full max-w-lg rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Edit guest') }}</h3>
                        <button type="button" wire:click="closeEdit"
                            class="h-8 w-8 rounded-xl border border-slate-200 dark:border-[#1e2433] text-slate-500 cursor-pointer">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>
                    <form wire:submit="saveGuest" class="space-y-3">
                        <div>
                            <x-label for="editName" :value="__('Name')" required />
                            <x-input id="editName" wire:model="editName" type="text" />
                            <x-input-error :messages="$errors->get('editName')" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <x-label for="editEmail" :value="__('Email')" />
                                <x-input id="editEmail" wire:model="editEmail" type="email" />
                                <x-input-error :messages="$errors->get('editEmail')" />
                            </div>
                            <div>
                                <x-label for="editPhone" :value="__('WhatsApp / Phone')" />
                                <x-input id="editPhone" wire:model="editPhone" type="text" />
                                <x-input-error :messages="$errors->get('editPhone')" />
                            </div>
                        </div>
                        <div>
                            <x-label for="editTagsInput" :value="__('Tags (comma separated)')" />
                            <x-input id="editTagsInput" wire:model="editTagsInput" type="text"
                                placeholder="VIP, Vegetarian" />
                        </div>
                        <div>
                            <x-label for="editNotes" :value="__('CRM Notes')" />
                            <x-textarea id="editNotes" wire:model="editNotes" rows="4" />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-button type="button" variant="secondary" wire:click="closeEdit">{{ __('Cancel') }}</x-button>
                            <x-button type="submit" variant="primary">{{ __('Save') }}</x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif
</div>
