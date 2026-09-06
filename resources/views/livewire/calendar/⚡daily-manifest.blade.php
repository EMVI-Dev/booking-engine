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
        abort_unless($this->currentOperator?->hasFeature('daily_manifest_export') ?? false, 403);

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
        if (!$this->currentOperator) {
            return collect();
        }

        return $this->currentOperator
            ->reservations()
            ->whereDate('requested_date', $this->manifestDate)
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::Completed->value, ReservationStatus::PendingConfirmation->value])
            ->with(['bookable', 'guest'])
            ->orderBy('created_at', 'asc')
            ->get();
    }
};
?>

<div class="space-y-6">
    <!-- Dedicated Print Stylesheet -->
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm 12mm 12mm 12mm;
            }

            body {
                background: #ffffff !important;
                color: #0f172a !important;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
                font-size: 11px !important;
            }

            .manifest-print-table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            .manifest-print-table th {
                background-color: #f1f5f9 !important;
                color: #0f172a !important;
                border: 1px solid #94a3b8 !important;
                padding: 6px 8px !important;
                font-size: 10px !important;
                font-weight: 800 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.05em !important;
            }

            .manifest-print-table td {
                border: 1px solid #cbd5e1 !important;
                padding: 6px 8px !important;
                color: #0f172a !important;
                font-size: 11px !important;
            }

            .manifest-print-table thead {
                display: table-header-group !important;
            }

            .manifest-print-table tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>

    <!-- Manifest Date Filter & Interactive Actions (Hidden on Print) -->
    <div
        class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs print:hidden">
        <div class="flex items-center gap-3">
            <span class="p-2 rounded-xl bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-300 text-sm">
                <i class="fa-solid fa-clipboard-list"></i>
            </span>
            <div>
                <h3 class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white leading-tight">
                    {{ __('Daily Passenger Run-Sheet') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ \Illuminate\Support\Carbon::parse($manifestDate)->format('l, F d, Y') }} &bull;
                    {{ $this->manifestReservations->sum('pax_count') }} {{ __('Total Passengers') }}
                    ({{ $this->manifestReservations->count() }} {{ __('Bookings') }})
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-stretch sm:self-auto justify-between sm:justify-end flex-wrap">
            <div class="flex items-center gap-1.5 min-w-[170px]">
                <x-date-picker wire:model.live="manifestDate" :presets="false" />
                <button type="button" wire:click="today"
                    class="px-2.5 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                    {{ __('Today') }}
                </button>
            </div>

            <div class="flex items-center gap-1 bg-slate-100 dark:bg-zinc-800 p-1 rounded-xl">
                <button type="button" wire:click="prevDay"
                    class="h-7 w-7 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                    title="{{ __('Previous Day') }}">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button type="button" wire:click="nextDay"
                    class="h-7 w-7 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-zinc-700 shadow-2xs transition cursor-pointer"
                    title="{{ __('Next Day') }}">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>

            <!-- Print / Export Manifest Button -->
            <button type="button" onclick="window.print()"
                class="h-9 px-4 rounded-xl bg-brand-400 hover:bg-brand-500 text-brand-foreground font-semibold text-xs transition flex items-center gap-1.5 cursor-pointer shrink-0">
                <i class="fa-solid fa-print"></i>
                <span>{{ __('Print Manifest') }}</span>
            </button>
        </div>
    </div>

    <!-- Daily Manifest Document Card -->
    <div
        class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-hidden print:border-none print:shadow-none print:p-0">
        <!-- Document Header -->
        <div
            class="p-6 border-b border-slate-200/80 dark:border-zinc-800 flex items-center justify-between bg-slate-50/50 dark:bg-zinc-800/30 print:bg-white print:border-b-2 print:border-slate-800 print:px-0 print:py-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span
                        class="text-xs font-black tracking-wider uppercase text-indigo-600 dark:text-indigo-400 print:text-slate-800">
                        {{ $this->currentOperator->name ?? config('app.name') }}
                    </span>
                    <span class="text-slate-300 print:text-slate-500">&bull;</span>
                    <span class="text-xs font-bold text-slate-500 print:text-slate-600">
                        {{ __('Official Passenger Run-Sheet') }}
                    </span>
                </div>
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white print:text-slate-900">
                    {{ \Illuminate\Support\Carbon::parse($manifestDate)->format('l, d F Y') }}
                </h2>
                <p class="text-xs text-slate-500 print:text-slate-600 mt-0.5">
                    {{ __('Generated:') }} {{ now()->format('d M Y, H:i') }} &bull;
                    {{ $this->manifestReservations->count() }} {{ __('Total Bookings') }}
                </p>
            </div>
            <div class="text-right">
                <div
                    class="inline-flex flex-col items-center justify-center px-4 py-2 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 print:border-2 print:border-slate-800 print:bg-slate-100">
                    <span
                        class="text-2xl sm:text-3xl font-black text-indigo-600 dark:text-indigo-400 print:text-slate-900 leading-none">
                        {{ $this->manifestReservations->sum('pax_count') }}
                    </span>
                    <span
                        class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 print:text-slate-700 mt-1">
                        {{ __('Total Pax') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto print:overflow-visible">
            <table
                class="w-full text-left text-xs sm:text-sm border-collapse min-w-[700px] print:min-w-full manifest-print-table">
                <thead>
                    <tr
                        class="border-b border-slate-200/80 dark:border-[#1e2433] bg-slate-50 dark:bg-[#10141d] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        <th class="py-3.5 px-3 font-bold uppercase tracking-wider text-[10px] w-10 text-center">#</th>
                        <!-- Print-only Checkbox Box for Clipboard Check-In -->
                        <th
                            class="hidden print:table-cell py-3.5 px-2 font-bold uppercase tracking-wider text-[9px] w-12 text-center">
                            {{ __('Check') }}</th>
                        <th class="py-3.5 px-3 font-bold uppercase tracking-wider text-[11px]">{{ __('Booking Code') }}
                        </th>
                        <th class="py-3.5 px-3 font-bold uppercase tracking-wider text-[11px]">
                            {{ __('Lead Guest & Contact') }}</th>
                        <th class="py-3.5 px-3 font-bold uppercase tracking-wider text-[11px]">
                            {{ __('Tour / Experience') }}</th>
                        <th class="py-3.5 px-3 font-bold uppercase tracking-wider text-[11px] text-center w-14">
                            {{ __('Pax') }}</th>
                        <th class="py-3.5 px-3 font-bold uppercase tracking-wider text-[11px]">
                            {{ __('Notes / Pickup Location') }}</th>
                        <th class="py-3.5 px-3 font-bold uppercase tracking-wider text-[11px] text-center w-24">
                            {{ __('Status') }}</th>
                        <th
                            class="py-3.5 px-3 font-bold uppercase tracking-wider text-[11px] text-center print:hidden w-20">
                            {{ __('WhatsApp') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                    @forelse ($this->manifestReservations as $idx => $res)
                        @php
                            $waUrl =
                                'https://wa.me/' .
                                preg_replace('/[^0-9]/', '', (string) $res->guest_contact) .
                                '?text=' .
                                urlencode(
                                    "Halo {$res->guest_name}, mengonfirmasi keberangkatan tur Anda hari ini #{$res->code}.",
                                );
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition group">
                            <td class="py-3.5 px-3 text-center font-mono font-bold text-slate-400 print:text-slate-900">
                                {{ $idx + 1 }}</td>

                            <!-- Print-only Checkbox for manual pen tick -->
                            <td class="hidden print:table-cell py-3.5 px-2 text-center">
                                <div class="w-4 h-4 mx-auto border border-slate-400 rounded-xs"></div>
                            </td>

                            <td class="py-3.5 px-3 whitespace-nowrap">
                                <span
                                    class="font-mono text-[10px] font-bold text-slate-800 dark:text-[#FFEF4D] px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-[#FFEF4D]/10 border border-slate-200 dark:border-[#FFEF4D]/30">
                                    #{{ $res->code }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3">
                                <p class="font-bold text-slate-900 dark:text-white print:text-slate-900 leading-tight">
                                    {{ $res->guest_name }}</p>
                                <p class="text-[11px] text-slate-500 print:text-slate-700 font-mono mt-0.5">
                                    {{ $res->guest_contact ?: '—' }}</p>
                            </td>
                            <td class="py-3.5 px-3">
                                <span class="font-bold text-slate-800 dark:text-zinc-200 print:text-slate-900">
                                    {{ $res->bookable?->name ?? __('Tour') }}
                                </span>
                            </td>
                            <td
                                class="py-3.5 px-3 text-center font-mono font-bold text-sm text-slate-900 dark:text-white print:text-slate-900">
                                {{ $res->pax_count }}
                            </td>
                            <td
                                class="py-3.5 px-3 text-slate-600 dark:text-slate-300 print:text-slate-800 text-xs max-w-xs">
                                {{ $res->notes ?: __('—') }}
                            </td>
                            <td class="py-3.5 px-3 text-center">
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold print:border print:border-slate-400 print:text-slate-900 print:bg-white
                                    {{ $res->status === \App\Enums\ReservationStatus::Confirmed ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-800/60' }}">
                                    @if ($res->status === \App\Enums\ReservationStatus::Confirmed)
                                        <span
                                            class="w-1.5 h-1.5 rounded-full bg-emerald-600 dark:bg-emerald-400"></span>
                                    @else
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600 dark:bg-amber-400"></span>
                                    @endif
                                    {{ $res->status->label() }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center print:hidden">
                                @if ($res->guest_contact)
                                    <a href="{{ $waUrl }}" target="_blank"
                                        class="h-7 px-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-[11px] transition inline-flex items-center gap-1 shadow-2xs"
                                        title="{{ __('Chat on WhatsApp') }}">
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
                            <td colspan="9" class="py-12 text-center text-slate-400 print:text-slate-600">
                                <i
                                    class="fa-solid fa-clipboard-check text-3xl mb-2 text-slate-300 dark:text-zinc-700 print:hidden block"></i>
                                {{ __('No passenger reservations scheduled for this departure date.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Ground Staff Sign-Off Block (Visible on Print Only) -->
        <div class="hidden print:grid grid-cols-3 gap-6 pt-8 mt-6 border-t border-slate-300 text-xs text-slate-700">
            <div class="space-y-6">
                <p class="font-bold uppercase text-[10px] tracking-wider text-slate-500">
                    {{ __('Tour Guide / Lead Staff') }}</p>
                <div class="border-b border-slate-400 pt-6"></div>
                <p class="text-[10px] text-slate-400">{{ __('Name & Signature') }}</p>
            </div>
            <div class="space-y-6">
                <p class="font-bold uppercase text-[10px] tracking-wider text-slate-500">
                    {{ __('Driver / Dispatch Check') }}</p>
                <div class="border-b border-slate-400 pt-6"></div>
                <p class="text-[10px] text-slate-400">{{ __('Name & Vehicle / Unit ID') }}</p>
            </div>
            <div class="space-y-6">
                <p class="font-bold uppercase text-[10px] tracking-wider text-slate-500">{{ __('Departure Verified') }}
                </p>
                <div class="border-b border-slate-400 pt-6"></div>
                <p class="text-[10px] text-slate-400">{{ __('Time & Dispatch Officer') }}</p>
            </div>
        </div>
    </div>
</div>
