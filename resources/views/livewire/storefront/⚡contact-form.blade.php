<?php

use App\Models\Enquiry;
use App\Models\Operator;
use App\Services\EnquiryService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Storefront contact / private-group form (paid plans, when the operator switched it on).
 * Free spam protection: a hidden honeypot field, a minimum fill time, and EnquiryService's
 * per-visitor rate limit. No CAPTCHA, so nothing to pay and no puzzle for guests.
 */
new class extends Component {
    public const MIN_SECONDS = 3;

    #[Locked]
    public Operator $operator;

    #[Locked]
    public int $startedAt = 0;

    public string $type = Enquiry::TYPE_GENERAL;

    public string $name = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $preferred_date = '';

    public ?int $group_size = null;

    public string $message = '';

    /** Honeypot: hidden from people, filled in by bots. */
    public string $website = '';

    public bool $sent = false;

    public function mount(Operator $operator): void
    {
        $this->operator = $operator;
        $this->startedAt = now()->getTimestamp();
    }

    public function send(EnquiryService $enquiries): void
    {
        $isGroup = $this->type === Enquiry::TYPE_PRIVATE_GROUP;

        $validated = $this->validate([
            'type' => ['required', Rule::in(Enquiry::TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'whatsapp' => ['required', 'string', 'min:8', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'preferred_date' => [$isGroup ? 'nullable' : 'exclude', 'date', 'after_or_equal:today'],
            'group_size' => [$isGroup ? 'nullable' : 'exclude', 'integer', 'min:1', 'max:500'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        // Bots: pretend it worked, store nothing.
        if ($this->website !== '' || now()->getTimestamp() - $this->startedAt < self::MIN_SECONDS) {
            $this->sent = true;

            return;
        }

        try {
            $enquiries->submit($this->operator, $validated, sha1((string) request()->ip()));
        } catch (ValidationException $e) {
            $this->addError('message', (string) collect($e->errors())->flatten()->first());

            return;
        }

        $this->sent = true;
        $this->reset('name', 'whatsapp', 'email', 'preferred_date', 'group_size', 'message');
    }
}; ?>

<section id="contact" class="space-y-5 pt-6 border-t border-slate-200/80 dark:border-zinc-800" aria-label="{{ __('Contact us') }}">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                {{ __('Contact & Group Enquiries') }}
            </h2>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Have a question or planning a private trip? Send us your details and we will reply directly on WhatsApp.') }}
            </p>
        </div>
    </div>

    @if ($sent)
        <div class="rounded-2xl sm:rounded-3xl border border-emerald-200/80 dark:border-emerald-800/80 bg-gradient-to-br from-emerald-50 via-white to-emerald-50/50 dark:from-emerald-950/30 dark:via-zinc-900 dark:to-zinc-900 p-8 sm:p-10 text-center space-y-4 shadow-xs">
            <div class="w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 mx-auto flex items-center justify-center text-3xl shadow-xs">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="space-y-1.5 max-w-md mx-auto">
                <h3 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white">
                    {{ __('Message Sent Successfully') }}
                </h3>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    {{ __('Thank you! Your message was sent. We will reply on WhatsApp soon.') }}
                </p>
            </div>
            <div class="pt-2">
                <button type="button" wire:click="$set('sent', false)"
                    class="h-10 px-5 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-xs font-bold text-slate-700 dark:text-slate-200 transition cursor-pointer">
                    <i class="fa-solid fa-arrow-rotate-left text-[11px]"></i>
                    <span>{{ __('Send Another Enquiry') }}</span>
                </button>
            </div>
        </div>
    @else
        <form wire:submit="send" class="space-y-6 rounded-2xl sm:rounded-3xl border border-slate-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 sm:p-8 shadow-xs">
            {{-- Segmented Type Choice --}}
            <div class="space-y-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    {{ __('Enquiry Type') }}
                </label>
                <div class="inline-flex p-1 rounded-2xl bg-slate-100 dark:bg-zinc-800/80 border border-slate-200/60 dark:border-zinc-700/60" role="radiogroup" aria-label="{{ __('What can we help with?') }}">
                    <label class="cursor-pointer">
                        <input type="radio" wire:model.live="type" value="{{ \App\Models\Enquiry::TYPE_GENERAL }}" class="sr-only" />
                        <span @class([
                            'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition select-none',
                            'bg-white dark:bg-zinc-900 text-slate-900 dark:text-white shadow-xs' => $type === \App\Models\Enquiry::TYPE_GENERAL,
                            'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' => $type !== \App\Models\Enquiry::TYPE_GENERAL,
                        ])>
                            <i class="fa-solid fa-circle-question text-xs"></i>
                            <span>{{ __('A question') }}</span>
                        </span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" wire:model.live="type" value="{{ \App\Models\Enquiry::TYPE_PRIVATE_GROUP }}" class="sr-only" />
                        <span @class([
                            'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition select-none',
                            'bg-white dark:bg-zinc-900 text-slate-900 dark:text-white shadow-xs' => $type === \App\Models\Enquiry::TYPE_PRIVATE_GROUP,
                            'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' => $type !== \App\Models\Enquiry::TYPE_PRIVATE_GROUP,
                        ])>
                            <i class="fa-solid fa-users text-xs"></i>
                            <span>{{ __('Private / group trip') }}</span>
                        </span>
                    </label>
                </div>
            </div>

            {{-- Form Inputs Grid --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Your Name') }} <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">
                            <i class="fa-solid fa-user"></i>
                        </span>
                        <input type="text" wire:model="name" placeholder="{{ __('Your full name') }}" autocomplete="name"
                            class="h-11 w-full pl-9 pr-3.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition" />
                    </div>
                    @error('name') <p class="text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('WhatsApp Number') }} <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-emerald-600 dark:text-emerald-400 text-sm">
                            <i class="fa-brands fa-whatsapp"></i>
                        </span>
                        <input type="tel" wire:model="whatsapp" placeholder="{{ __('e.g. +62 812 3456 7890') }}" autocomplete="tel"
                            class="h-11 w-full pl-9 pr-3.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition" />
                    </div>
                    @error('whatsapp') <p class="text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2 space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ __('Email Address') }} <span class="text-xs font-normal text-slate-400">({{ __('Optional') }})</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" wire:model="email" placeholder="{{ __('your.email@example.com') }}" autocomplete="email"
                            class="h-11 w-full pl-9 pr-3.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition" />
                    </div>
                    @error('email') <p class="text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                </div>

                @if ($type === \App\Models\Enquiry::TYPE_PRIVATE_GROUP)
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            {{ __('Preferred Departure Date') }}
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-regular fa-calendar"></i>
                            </span>
                            <input type="date" wire:model="preferred_date" min="{{ now()->toDateString() }}"
                                class="h-11 w-full pl-9 pr-3.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 text-sm text-slate-900 dark:text-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition" />
                        </div>
                        @error('preferred_date') <p class="text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            {{ __('Group Size (Guests)') }}
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-solid fa-users"></i>
                            </span>
                            <input type="number" wire:model="group_size" min="1" max="500" placeholder="{{ __('e.g. 8') }}"
                                class="h-11 w-full pl-9 pr-3.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition" />
                        </div>
                        @error('group_size') <p class="text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                    {{ __('Your Message') }} <span class="text-rose-500">*</span>
                </label>
                <textarea wire:model="message" rows="4"
                    placeholder="{{ $type === \App\Models\Enquiry::TYPE_PRIVATE_GROUP ? __('Tell us about your group, preferred destinations, activities, and any special requirements...') : __('Please write your question or inquiry here...') }}"
                    class="w-full rounded-xl sm:rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 p-3.5 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition leading-relaxed"></textarea>
                @error('message') <p class="text-xs text-rose-600 font-medium">{{ $message }}</p> @enderror
            </div>

            {{-- Honeypot: kept off-screen; people never see or fill it. --}}
            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label>{{ __('Website') }} <input type="text" wire:model="website" tabindex="-1" autocomplete="off" /></label>
            </div>

            <div class="pt-1">
                <button type="submit" wire:loading.attr="disabled"
                    class="h-11 sm:h-12 px-7 inline-flex items-center justify-center gap-2 rounded-xl sm:rounded-2xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-brand-foreground font-bold text-xs sm:text-sm shadow-xs hover:shadow-md transition cursor-pointer disabled:opacity-60">
                    <span wire:loading.remove wire:target="send" class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span>{{ __('Send Message') }}</span>
                    </span>
                    <span wire:loading wire:target="send" class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-spinner fa-spin text-xs"></i>
                        <span>{{ __('Sending...') }}</span>
                    </span>
                </button>
            </div>
        </form>
    @endif
</section>
