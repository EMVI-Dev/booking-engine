@php
    $operator = auth()->user()?->currentOperator();
    $currentPlan = $operator?->getPlan();
@endphp

<div class="space-y-4">
    <!-- Subscription & Billing Navigation Tab Bar -->
    <div class="flex items-center gap-1.5 p-1.5 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-x-auto whitespace-nowrap select-none no-scrollbar">
        <a
            href="{{ route('settings.plan') }}"
            wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2 rounded-xl text-xs sm:text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('settings.plan', 'settings.plan.checkout') ? 'bg-purple-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}"
        >
            <i class="fa-solid fa-crown text-xs {{ request()->routeIs('settings.plan', 'settings.plan.checkout') ? 'text-amber-300' : 'text-purple-500' }}"></i>
            <span>{{ __('Subscription & Plan') }}</span>
            @if ($currentPlan)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ request()->routeIs('settings.plan', 'settings.plan.checkout') ? 'bg-purple-700 text-white' : 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300' }}">
                    {{ $currentPlan->name }}
                </span>
            @endif
        </a>

        <a
            href="{{ route('settings.billing') }}"
            wire:navigate
            class="h-10 px-4 inline-flex items-center gap-2 rounded-xl text-xs sm:text-sm font-semibold transition-all duration-150 shrink-0 {{ request()->routeIs('settings.billing') ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800/60 hover:text-slate-900 dark:hover:text-white' }}"
        >
            <i class="fa-solid fa-file-invoice-dollar text-xs {{ request()->routeIs('settings.billing') ? 'text-white' : 'text-indigo-500' }}"></i>
            <span>{{ __('Billing & Invoices') }}</span>
        </a>
    </div>
</div>
