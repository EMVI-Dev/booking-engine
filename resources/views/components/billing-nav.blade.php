@php
    $operator = auth()->user()?->currentOperator();
    $currentPlan = $operator?->getPlan();
@endphp

<div class="space-y-4">
    <!-- Subscription & Billing Navigation Tab Bar -->
    <div
        class="flex items-center gap-1.5 p-1.5 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs overflow-x-auto whitespace-nowrap select-none no-scrollbar">
        <a href="{{ route('settings.plan') }}" wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2 rounded-xl text-xs sm:text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('settings.plan', 'settings.plan.checkout') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-[#141721] hover:text-slate-900 dark:hover:text-white' }}">
            <i
                class="fa-solid fa-crown text-xs {{ request()->routeIs('settings.plan', 'settings.plan.checkout') ? 'text-[#090d16]' : 'text-[#FFEF4D]' }}"></i>
            <span>{{ __('Subscription & Plan') }}</span>
            @if ($currentPlan)
                <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-black {{ request()->routeIs('settings.plan', 'settings.plan.checkout') ? 'bg-[#090d16] text-[#FFEF4D]' : 'bg-[#FFEF4D]/15 text-slate-950 border border-slate-950/30' }}">
                    {{ $currentPlan->name }}
                </span>
            @endif
        </a>

        <a href="{{ route('settings.billing') }}" wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2 rounded-xl text-xs sm:text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('settings.billing') ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-[#141721] hover:text-slate-900 dark:hover:text-white' }}">
            <i
                class="fa-solid fa-file-invoice-dollar text-xs {{ request()->routeIs('settings.billing') ? 'text-[#090d16]' : 'text-slate-400 dark:text-zinc-500' }}"></i>
            <span>{{ __('Billing & Invoices') }}</span>
        </a>
    </div>
</div>
