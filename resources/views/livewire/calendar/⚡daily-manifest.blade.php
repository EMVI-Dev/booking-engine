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
        class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none print:hidden">
        <div class="flex items-center gap-3">
            <span class="w-9 h-9 rounded-[8px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-sm shrink-0">
                <i class="fa-solid fa-clipboard-list"></i>
            </span>
            <div>
                <h3 class="text-[16px] sm:text-[18px] font-medium text-[#12181E] dark:text-white leading-tight">
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
                    class="h-9 px-3 rounded-[6px] text-xs font-medium bg-[#F9FAFB] dark:bg-[#151a26] hover:bg-[#F3F4F6] dark:hover:bg-[#1E2433] text-slate-700 dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] transition cursor-pointer">
                    {{ __('Today') }}
                </button>
            </div>

            <div class="flex items-center gap-1 bg-[#F9FAFB] dark:bg-[#151a26] p-1 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433]">
                <button type="button" wire:click="prevDay"
                    class="h-7 w-7 rounded-[4px] flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-[#10141d] transition cursor-pointer"
                    title="{{ __('Previous Day') }}">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button type="button" wire:click="nextDay"
                    class="h-7 w-7 rounded-[4px] flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-[#10141d] transition cursor-pointer"
                    title="{{ __('Next Day') }}">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
            </div>

            <!-- Print / Export Manifest Button -->
            <button type="button" onclick="window.print()"
                class="h-9 px-4 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-medium text-xs transition flex items-center gap-1.5 cursor-pointer shrink-0 shadow-none">
                <i class="fa-solid fa-print"></i>
                <span>{{ __('Print Manifest') }}</span>
            </button>
        </div>
    </div>

    <!-- Daily Manifest Document Card -->
    <div
        class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden print:border-none print:shadow-none print:p-0">
        <!-- Document Header -->
        <div
            class="p-4 sm:p-6 border-b border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-between bg-[#F9FAFB] dark:bg-[#151a26] print:bg-white print:border-b-2 print:border-slate-800 print:px-0 print:py-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span
                        class="text-xs font-medium tracking-wider uppercase text-slate-700 dark:text-slate-300 print:text-slate-800">
                        {{ $this->currentOperator->name ?? config('app.name') }}
                    </span>
                    <span class="text-slate-300 print:text-slate-500">&bull;</span>
                    <span class="text-xs font-medium text-slate-500 print:text-slate-600">
                        {{ __('Official Passenger Run-Sheet') }}
                    </span>
                </div>
                <h2 class="text-lg sm:text-xl font-medium text-[#12181E] dark:text-white print:text-slate-900">
                    {{ \Illuminate\Support\Carbon::parse($manifestDate)->format('l, d F Y') }}
                </h2>
                <p class="text-xs text-slate-500 print:text-slate-600 mt-0.5">
                    {{ __('Generated:') }} {{ now()->format('d M Y, H:i') }} &bull;
                    {{ $this->manifestReservations->count() }} {{ __('Total Bookings') }}
                </p>
            </div>
            <div class="text-right">
                <div
                    class="inline-flex flex-col items-center justify-center px-4 py-2 rounded-[8px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] print:border-2 print:border-slate-800 print:bg-slate-100">
                    <span
                        class="text-2xl font-medium text-[#12181E] dark:text-white print:text-slate-900 leading-none">
                        {{ $this->manifestReservations->sum('pax_count') }}
                    </span>
                    <span
                        class="text-[10px] font-medium uppercase tracking-wider text-slate-500 print:text-slate-700 mt-1">
                        {{ __('Total Pax') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Mobile Card List View (Phones) -->
        <div class="md:hidden divide-y divide-[#E4E5E9] dark:divide-[#1E2433] print:hidden">
            @forelse ($this->manifestReservations as $idx => $res)
                @php
                    $waUrl = 'https://wa.me/' . \App\Services\PhoneNumber::normalize((string) $res->guest_contact) . '?text=' . urlencode("Halo {$res->guest_name}, mengonfirmasi keberangkatan tur Anda hari ini #{$res->code}.");
                @endphp
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-[4px] bg-[#F9FAFB] dark:bg-[#151a26] border border-[#E4E5E9] dark:border-[#1E2433] text-[10px] font-mono text-slate-500 flex items-center justify-center">
                                {{ $idx + 1 }}
                            </span>
                            <span class="font-mono text-[10px] font-medium text-[#12181E] dark:text-[#FFEF4D] px-2 py-0.5 rounded-[4px] bg-[#F9FAFB] dark:bg-[#FFEF4D]/10 border border-[#E4E5E9] dark:border-[#FFEF4D]/30">
                                #{{ $res->code }}
                            </span>
                        </div>
                        <x-status-badge :status="$res->status" />
                    </div>

                    <div class="space-y-1">
                        <p class="font-medium text-sm text-[#12181E] dark:text-white">
                            {{ $res->guest_name }}
                        </p>
                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            {{ $res->bookable?->name ?? __('Tour') }} &bull;
                            <span class="font-medium text-[#12181E] dark:text-white">{{ $res->pax_count }} pax</span>
                        </p>
                        @if ($res->notes)
                            <p class="text-[11px] text-slate-500 italic bg-[#F9FAFB] dark:bg-[#151a26] p-2 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433]">
                                {{ $res->notes }}
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <span class="text-xs font-mono text-slate-500">{{ $res->guest_contact ?: '—' }}</span>
                        @if ($res->guest_contact)
                            <a href="{{ $waUrl }}" target="_blank"
                                class="h-8 px-3 rounded-[6px] bg-emerald-500 hover:bg-emerald-600 text-white font-medium text-xs transition inline-flex items-center gap-1.5 shadow-none">
                                <i class="fa-brands fa-whatsapp text-sm"></i>
                                <span>{{ __('WhatsApp') }}</span>
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-10 text-center text-slate-400 p-4">
                    <i class="fa-solid fa-clipboard-check text-2xl mb-2 text-slate-300 dark:text-zinc-700 block"></i>
                    <p class="text-xs">{{ __('No passenger reservations scheduled for this departure date.') }}</p>
                </div>
            @endforelse
        </div>

        <!-- Desktop & Print Table View -->
        <div class="hidden md:block overflow-x-auto print:block print:overflow-visible">
            <table
                class="w-full text-left text-xs sm:text-sm border-collapse min-w-[700px] print:min-w-full manifest-print-table">
                <thead>
                    <tr
                        class="border-b border-[#E4E5E9] dark:border-[#1E2433] bg-[#F9FAFB] dark:bg-[#151a26] text-[11px] font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        <th class="py-3.5 px-3 font-medium uppercase tracking-wider text-[10px] w-10 text-center">#</th>
                        <!-- Print-only Checkbox Box for Clipboard Check-In -->
                        <th
                            class="hidden print:table-cell py-3.5 px-2 font-medium uppercase tracking-wider text-[9px] w-12 text-center">
                            {{ __('Check') }}</th>
                        <th class="py-3.5 px-3 font-medium uppercase tracking-wider text-[11px]">{{ __('Booking Code') }}
                        </th>
                        <th class="py-3.5 px-3 font-medium uppercase tracking-wider text-[11px]">
                            {{ __('Lead Guest & Contact') }}</th>
                        <th class="py-3.5 px-3 font-medium uppercase tracking-wider text-[11px]">
                            {{ __('Tour / Experience') }}</th>
                        <th class="py-3.5 px-3 font-medium uppercase tracking-wider text-[11px] text-center w-14">
                            {{ __('Pax') }}</th>
                        <th class="py-3.5 px-3 font-medium uppercase tracking-wider text-[11px]">
                            {{ __('Notes / Pickup Location') }}</th>
                        <th class="py-3.5 px-3 font-medium uppercase tracking-wider text-[11px] text-center w-28">
                            {{ __('Status') }}</th>
                        <th
                            class="py-3.5 px-3 font-medium uppercase tracking-wider text-[11px] text-center print:hidden w-24">
                            {{ __('WhatsApp') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                    @forelse ($this->manifestReservations as $idx => $res)
                        @php
                            $waUrl =
                                'https://wa.me/' .
                                \App\Services\PhoneNumber::normalize((string) $res->guest_contact) .
                                '?text=' .
                                urlencode(
                                    "Halo {$res->guest_name}, mengonfirmasi keberangkatan tur Anda hari ini #{$res->code}.",
                                );
                        @endphp
                        <tr class="hover:bg-[#F9FAFB] dark:hover:bg-[#151a26] transition group">
                            <td class="py-3.5 px-3 text-center font-mono font-medium text-slate-400 print:text-slate-900">
                                {{ $idx + 1 }}</td>

                            <!-- Print-only Checkbox for manual pen tick -->
                            <td class="hidden print:table-cell py-3.5 px-2 text-center">
                                <div class="w-4 h-4 mx-auto border border-slate-400 rounded-[2px]"></div>
                            </td>

                            <td class="py-3.5 px-3 whitespace-nowrap">
                                <span
                                    class="font-mono text-[10px] font-medium text-[#12181E] dark:text-[#FFEF4D] px-2 py-0.5 rounded-[4px] bg-[#F9FAFB] dark:bg-[#FFEF4D]/10 border border-[#E4E5E9] dark:border-[#FFEF4D]/30">
                                    #{{ $res->code }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3">
                                <p class="font-medium text-[#12181E] dark:text-white print:text-slate-900 leading-tight">
                                    {{ $res->guest_name }}</p>
                                <p class="text-[11px] text-slate-500 print:text-slate-700 font-mono mt-0.5">
                                    {{ $res->guest_contact ?: '—' }}</p>
                            </td>
                            <td class="py-3.5 px-3">
                                <span class="font-medium text-slate-800 dark:text-zinc-200 print:text-slate-900">
                                    {{ $res->bookable?->name ?? __('Tour') }}
                                </span>
                            </td>
                            <td
                                class="py-3.5 px-3 text-center font-mono font-medium text-sm text-[#12181E] dark:text-white print:text-slate-900">
                                {{ $res->pax_count }}
                            </td>
                            <td
                                class="py-3.5 px-3 text-slate-600 dark:text-slate-300 print:text-slate-800 text-xs max-w-xs">
                                {{ $res->notes ?: __('—') }}
                            </td>
                            <td class="py-3.5 px-3 text-center">
                                <x-status-badge :status="$res->status" />
                            </td>
                            <td class="py-3 px-3 text-center print:hidden">
                                @if ($res->guest_contact)
                                    <a href="{{ $waUrl }}" target="_blank"
                                        class="h-8 px-2.5 rounded-[6px] bg-emerald-500 hover:bg-emerald-600 text-white font-medium text-xs transition inline-flex items-center gap-1.5 shadow-none"
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
