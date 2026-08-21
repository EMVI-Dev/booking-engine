@props([
    'title' => __('Feature Locked'),
    'description' => __('This feature is available exclusively on higher tier subscription plans.'),
    'requiredPlan' => 'Pro Operator',
    'planSlug' => 'growth',
    'icon' => 'fa-solid fa-lock',
    'features' => [],
])

<div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-sm p-8 sm:p-12 text-center max-w-2xl mx-auto space-y-6">
    <!-- Feature Icon with Lock Badge -->
    <div class="relative inline-block mx-auto">
        <div class="w-20 h-20 rounded-3xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 flex items-center justify-center text-3xl shadow-inner mx-auto">
            <i class="{{ $icon }}"></i>
        </div>
        <div class="absolute -bottom-1 -right-1 w-8 h-8 rounded-full bg-amber-500 text-white flex items-center justify-center text-xs shadow-md border-2 border-white dark:border-zinc-900">
            <i class="fa-solid fa-lock"></i>
        </div>
    </div>

    <!-- Header Text -->
    <div class="space-y-2 max-w-lg mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
            <i class="fa-solid fa-sparkles text-[10px]"></i>
            <span>{{ __('Requires :plan Plan', ['plan' => $requiredPlan]) }}</span>
        </div>
        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
            {{ $title }}
        </h2>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
            {{ $description }}
        </p>
    </div>

    <!-- Feature Checklist Pills -->
    @if (!empty($features))
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-100 dark:border-zinc-800 text-left max-w-md mx-auto space-y-2">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block text-center">{{ __('What You Will Unlock:') }}</span>
            <ul class="space-y-1.5 text-xs text-slate-700 dark:text-slate-300">
                @foreach ($features as $f)
                    <li class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-500 text-xs shrink-0"></i>
                        <span>{{ $f }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Upgrade CTA Button -->
    <div class="pt-2">
        <a
            href="{{ route('settings.plan') }}"
            wire:navigate
            class="h-11 px-8 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-xs sm:text-sm shadow-md hover:shadow-lg transition-all inline-flex items-center gap-2"
        >
            <i class="fa-solid fa-bolt text-amber-300"></i>
            <span>{{ __('Upgrade to :plan', ['plan' => $requiredPlan]) }}</span>
            <i class="fa-solid fa-arrow-right text-xs"></i>
        </a>
        <p class="text-[11px] text-slate-400 mt-3">
            {{ __('Flexible monthly or annual plans. Upgrade, downgrade, or cancel anytime.') }}
        </p>
    </div>
</div>
