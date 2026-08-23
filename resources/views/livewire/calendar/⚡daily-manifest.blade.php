<?php

use App\Enums\ReservationStatus;
use App\Models\Operator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $manifestDate = '';

    public function mount(): void
    {
        $this->manifestDate = now()->toDateString();
    }

    #[Computed]
    public function currentOperator(): ?Operator
    {
        return auth()->user()?->currentOperator();
    }

    public function prevDay(): void
    {
        $this->manifestDate = Carbon::parse($this->manifestDate)->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $this->manifestDate = Carbon::parse($this->manifestDate)->addDay()->toDateString();
    }

    public function today(): void
    {
        $this->manifestDate = now()->toDateString();
    }

    /**
     * Daily manifest reservations for the selected date.
     *
     * @return Collection<int, \App\Models\Reservation>
     */
    #[Computed]
    public function manifestReservations(): Collection
    {
        if (! $this->currentOperator) {
            return collect();
        }

        return $this->currentOperator->reservations()
            ->whereDate('requested_date', $this->manifestDate)
            ->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::Completed->value,
                ReservationStatus::PendingConfirmation->value,
            ])
            ->with(['bookable', 'guest'])
            ->orderBy('created_at', 'asc')
            ->get();
    }
};
?>

<div class="space-y-6">
    <!-- Manifest Date Filter & Actions -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs print:hidden">
        <div class="flex items-center gap-3">
            <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 text-sm">
                <i class="fa-solid fa-clipboard-list"></i>
            </span>
            <div>
                <h3 class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white leading-tight">
                    {{ __('Daily Passenger Run-Sheet') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ \Illuminate\Support\Carbon::parse($manifestDate)->format('l, F d, Y') }} &bull; {{ $this->manifestReservations->sum('pax_count') }} {{ __('Total Passengers') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-stretch sm:self-auto justify-between sm:justify-end flex-wrap">
            <div class="flex items-center gap-1.5">
                <input
                    type="date"
                    wire:model.live="manifestDate"
                    class="h-9 px-3 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-800 text-xs font-bold text-slate-800 dark:text-zinc-200 cursor-pointer"
                />
                <button
                    type="button"
                    wire:click="today"
                    class="px-2.5 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer"
                >
                    {{ __('Today') }}
                </button>
            </div>

            <div class="flex items-center gap-1 bg-slate-100 dark:bg-zinc-800 p-1 rounded-xl">
                <button
                    type="button"
                    wire:click="prevDay"
                    class="h-7 w-7 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                >
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button
                    type="button"
                    wire:click="nextDay"
                    class="h-7 w-7 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                >
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>

            <!-- Print / Export Manifest Button -->
            <button
                type="button"
                onclick="window.print()"
                class="h-9 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-extrabold text-xs shadow-xs transition flex items-center gap-1.5 cursor-pointer shrink-0"
            >
                <i class="fa-solid fa-print"></i>
                <span>{{ __('Print Manifest') }}</span>
            </button>
        </div>
    </div>

    <!-- Printable Daily Manifest Run-Sheet -->
    <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-hidden print:border-none print:shadow-none">
        <!-- Print Header (Visible during Print or Screen) -->
        <div class="p-6 border-b border-slate-200/80 dark:border-zinc-800 flex items-center justify-between bg-slate-50/50 dark:bg-zinc-800/30">
            <div>
                <h2 class="text-xl font-black text-slate-900 dark:text-white">
                    {{ $this->currentOperator->name ?? config('app.name') }} &mdash; {{ __('Daily Tour Manifest') }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ __('Departure Date:') }} <strong class="text-slate-900 dark:text-white">{{ \Illuminate\Support\Carbon::parse($manifestDate)->format('l, d F Y') }}</strong>
                    &bull; {{ __('Generated on:') }} {{ now()->format('d M Y, H:i') }}
                </p>
            </div>
            <div class="text-right">
                <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                    {{ $this->manifestReservations->sum('pax_count') }}
                </span>
                <span class="text-xs font-bold text-slate-400 block">{{ __('Total Pax') }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse min-w-[700px]">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-zinc-800 bg-slate-100/70 dark:bg-zinc-800/60 text-slate-600 dark:text-zinc-400">
                        <th class="py-3 px-4 font-black uppercase tracking-wider text-[10px] w-12 text-center">#</th>
                        <th class="py-3 px-4 font-black uppercase tracking-wider text-[10px]">{{ __('Booking Code') }}</th>
                        <th class="py-3 px-4 font-black uppercase tracking-wider text-[10px]">{{ __('Lead Guest & Contact') }}</th>
                        <th class="py-3 px-4 font-black uppercase tracking-wider text-[10px]">{{ __('Tour / Experience') }}</th>
                        <th class="py-3 px-4 font-black uppercase tracking-wider text-[10px] text-center">{{ __('Pax') }}</th>
                        <th class="py-3 px-4 font-black uppercase tracking-wider text-[10px]">{{ __('Notes / Pickup Location') }}</th>
                        <th class="py-3 px-4 font-black uppercase tracking-wider text-[10px] text-center">{{ __('Status') }}</th>
                        <th class="py-3 px-4 font-black uppercase tracking-wider text-[10px] text-center print:hidden">{{ __('WhatsApp') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60">
                    @forelse ($this->manifestReservations as $idx => $res)
                        @php
                            $waUrl = 'https://wa.me/'.preg_replace('/[^0-9]/', '', (string)$res->guest_contact).'?text='.urlencode("Halo {$res->guest_name}, mengonfirmasi keberangkatan tur Anda hari ini #{$res->code}.");
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                            <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-400">{{ $idx + 1 }}</td>
                            <td class="py-3.5 px-4 font-mono font-extrabold text-indigo-600 dark:text-indigo-400">
                                #{{ $res->code }}
                            </td>
                            <td class="py-3.5 px-4">
                                <p class="font-extrabold text-slate-900 dark:text-white">{{ $res->guest_name }}</p>
                                <p class="text-[11px] text-slate-500 font-mono">{{ $res->guest_contact ?: '—' }}</p>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-bold text-slate-800 dark:text-zinc-200">
                                    {{ $res->bookable?->name ?? __('Tour') }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center font-black text-sm text-slate-900 dark:text-white">
                                {{ $res->pax_count }}
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300 max-w-xs truncate text-[11px]">
                                {{ $res->notes ?: __('No special pickup notes.') }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase
                                    {{ $res->status === \App\Enums\ReservationStatus::Confirmed ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                    {{ $res->status->label() }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center print:hidden">
                                @if ($res->guest_contact)
                                    <a
                                        href="{{ $waUrl }}"
                                        target="_blank"
                                        class="h-7 px-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-[11px] transition inline-flex items-center gap-1 shadow-2xs"
                                    >
                                        <i class="fa-brands fa-whatsapp text-xs"></i>
                                        <span>{{ __('Chat') }}</span>
                                    </a>
                                @else
                                    <span class="text-slate-300">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-clipboard-check text-3xl mb-2 text-slate-300 dark:text-zinc-700 block"></i>
                                {{ __('No passenger reservations scheduled for this departure date.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
