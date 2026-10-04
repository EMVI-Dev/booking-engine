<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Services\EnquiryService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Guest questions and private / group trip requests from the storefront contact form.
 * Kept here so nothing is lost if the operator never reads email. Rules: EnquiryService.
 */
new #[Title('Enquiries')] class extends Component {
    use ResolvesCurrentOperator;
    use WithPagination;

    public function mount(): void
    {
        $this->authorizeAbility('manageReservations');
    }

    #[Computed]
    public function enquiries()
    {
        return $this->currentOperator->enquiries()->latest()->paginate(20);
    }

    public function markRead(EnquiryService $service, string $enquiryId): void
    {
        $this->authorizeAbility('manageReservations');
        $service->markRead($this->currentOperator, $enquiryId);
        unset($this->enquiries);
    }

    public function delete(EnquiryService $service, string $enquiryId): void
    {
        $this->authorizeAbility('manageReservations');
        $service->delete($this->currentOperator, $enquiryId);
        unset($this->enquiries);
        $this->dispatch('toast', message: __('Enquiry deleted.'), type: 'success');
    }
}; ?>

<div class="space-y-6 w-full">
    <x-page-header
        :title="__('Enquiries')"
        :subtitle="__('Questions and private / group trip requests from your website.')"
        icon="fa-envelope-open-text"
    />

    @if ($this->enquiries->isEmpty())
        <div class="rounded-[12px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] p-10 text-center space-y-4">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-zinc-500 mx-auto flex items-center justify-center text-2xl">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <div class="space-y-1 max-w-sm mx-auto">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('No enquiries yet') }}</h3>
                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">
                    @if ($this->currentOperator->hasFeature('contact_form'))
                        {{ __('Turn on the contact form under Settings → Gallery, FAQ & Contact to accept questions from travelers.') }}
                    @else
                        {{ __('The contact form is available on paid plans. Guests can still reach you with the WhatsApp button.') }}
                    @endif
                </p>
            </div>
        </div>
    @else
        <div class="space-y-3.5">
            @foreach ($this->enquiries as $enquiry)
                <article wire:key="enquiry-{{ $enquiry->id }}" @class([
                    'rounded-[12px] p-5 sm:p-6 space-y-3.5 transition-all shadow-xs',
                    'border-2 border-[#FFEF4D] dark:border-[#FFEF4D]/70 bg-white dark:bg-[#10141d]' => $enquiry->read_at === null,
                    'border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d]' => $enquiry->read_at !== null,
                ])>
                    {{-- Header Row: Sender, Type, Status & Timestamp --}}
                    <div class="flex flex-wrap items-center justify-between gap-2.5">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300 font-black text-xs flex items-center justify-center shrink-0">
                                {{ strtoupper(substr($enquiry->name, 0, 1)) }}
                            </span>
                            <div class="flex flex-wrap items-center gap-2 min-w-0">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                                    {{ $enquiry->name }}
                                </h3>

                                @if ($enquiry->isPrivateGroup())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border border-purple-200/60 dark:border-purple-800/60">
                                        <i class="fa-solid fa-users text-[10px]"></i>
                                        <span>{{ $enquiry->typeLabel() }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border border-sky-200/60 dark:border-sky-800/60">
                                        <i class="fa-solid fa-circle-question text-[10px]"></i>
                                        <span>{{ $enquiry->typeLabel() }}</span>
                                    </span>
                                @endif

                                @if ($enquiry->read_at === null)
                                    <span class="rounded-full bg-[#FFEF4D] px-2 py-0.5 text-[10px] font-black uppercase text-[#12181E] tracking-wider shadow-xs">
                                        {{ __('New') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <span class="text-xs text-[#5A6578] dark:text-[#9DA4B2] flex items-center gap-1.5 shrink-0">
                            <i class="fa-regular fa-clock text-[11px]"></i>
                            <span>{{ $enquiry->created_at?->diffForHumans() }}</span>
                        </span>
                    </div>

                    {{-- Contact & Trip Meta Details Chips --}}
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $enquiry->whatsapp) }}" target="_blank" rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[6px] bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60 font-semibold hover:underline">
                            <i class="fa-brands fa-whatsapp text-emerald-600 text-xs"></i>
                            <span>{{ $enquiry->whatsapp }}</span>
                        </a>

                        @if ($enquiry->email)
                            <a href="mailto:{{ $enquiry->email }}"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[6px] bg-slate-50 dark:bg-[#141821] text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-zinc-800 font-semibold hover:underline">
                                <i class="fa-regular fa-envelope text-slate-400 text-xs"></i>
                                <span>{{ $enquiry->email }}</span>
                            </a>
                        @endif

                        @if ($enquiry->preferred_date)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[6px] bg-slate-50 dark:bg-[#141821] text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-zinc-800 font-semibold">
                                <i class="fa-regular fa-calendar text-slate-400 text-xs"></i>
                                <span>{{ __('Date: :date', ['date' => $enquiry->preferred_date->format('j M Y')]) }}</span>
                            </span>
                        @endif

                        @if ($enquiry->group_size)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[6px] bg-slate-50 dark:bg-[#141821] text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-zinc-800 font-semibold">
                                <i class="fa-solid fa-users text-slate-400 text-xs"></i>
                                <span>{{ __(':count people', ['count' => $enquiry->group_size]) }}</span>
                            </span>
                        @endif
                    </div>

                    {{-- Message Bubble --}}
                    <div class="p-4 rounded-[10px] bg-slate-50 dark:bg-[#141821] border border-slate-100 dark:border-zinc-800/80 text-xs sm:text-sm text-slate-800 dark:text-slate-200 leading-relaxed whitespace-pre-line">
                        {{ $enquiry->message }}
                    </div>

                    {{-- Actions Bar --}}
                    <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-[#E4E5E9]/60 dark:border-[#1E2433]/60">
                        <a href="{{ app(\App\Services\EnquiryService::class)->whatsAppReplyUrl($enquiry) }}" target="_blank" rel="noopener noreferrer"
                            wire:click="markRead('{{ $enquiry->id }}')"
                            class="h-8 sm:h-9 px-3.5 sm:px-4 inline-flex items-center gap-2 rounded-[6px] sm:rounded-[8px] bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs shadow-xs hover:shadow-sm transition cursor-pointer">
                            <i class="fa-brands fa-whatsapp text-sm"></i>
                            <span>{{ __('Reply on WhatsApp') }}</span>
                        </a>

                        @if ($enquiry->read_at === null)
                            <button type="button" wire:click="markRead('{{ $enquiry->id }}')"
                                class="h-8 sm:h-9 px-3 sm:px-3.5 inline-flex items-center gap-1.5 rounded-[6px] sm:rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] hover:bg-slate-50 dark:hover:bg-zinc-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition cursor-pointer">
                                <i class="fa-solid fa-check text-xs"></i>
                                <span>{{ __('Mark as read') }}</span>
                            </button>
                        @endif

                        <button type="button" wire:click="delete('{{ $enquiry->id }}')" wire:confirm="{{ __('Delete this enquiry?') }}"
                            class="h-8 sm:h-9 px-3 sm:px-3.5 inline-flex items-center gap-1.5 rounded-[6px] sm:rounded-[8px] text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer ml-auto">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                            <span>{{ __('Delete') }}</span>
                        </button>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="pt-2">
            {{ $this->enquiries->links() }}
        </div>
    @endif
</div>
