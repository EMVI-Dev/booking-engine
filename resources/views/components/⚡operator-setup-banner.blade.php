<?php

use App\Concerns\ResolvesCurrentOperator;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use ResolvesCurrentOperator;

    #[On('setup-progress-updated')]
    #[On('brand-updated')]
    #[On('storefront-updated')]
    #[On('payment-settings-updated')]
    #[On('reviews-updated')]
    public function refreshChecklist(): void
    {
        unset($this->currentOperator);
    }
}; ?>

<div>
    @php
        $operator = $this->currentOperator;
        $steps = $operator?->salesReadinessChecklist() ?? [];
        $remaining = collect($steps)->where('done', false)->values();
        $doneCount = collect($steps)->where('done', true)->count();
        $total = count($steps);
        $percent = $total > 0 ? (int) round(($doneCount / $total) * 100) : 0;
        $next = $remaining->first();
        $otherRemaining = $remaining->slice(1)->values();
        $hasOtherSteps = $otherRemaining->isNotEmpty();
        $remainingCount = $remaining->count();
    @endphp

    @if ($operator && $remaining->isNotEmpty())
        <div
            class="mb-4 overflow-hidden rounded-[12px] border border-[#E4E5E9] bg-white p-4 dark:border-[#1E2433] dark:bg-[#10141d]"
            role="status"
            aria-live="polite"
            wire:key="setup-banner-{{ $doneCount }}-{{ $remainingCount }}"
        >
            <div class="flex items-start gap-3">
                <span
                    class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-[#FFEF4D] text-[#12181E] shadow-none"
                    aria-hidden="true"
                >
                    <i class="fa-solid fa-list-check text-sm"></i>
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-op-ink">
                                @if ($remainingCount === 1)
                                    {{ __('One step left before guests can book') }}
                                @else
                                    {{ __('Finish setup to take bookings') }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs leading-relaxed text-op-subtle">
                                {{ __('Guests cannot pay you until these are done.') }}
                            </p>
                        </div>
                        <p class="shrink-0 rounded-[4px] border border-[#E4E5E9] bg-[#F7F8F9] px-2 py-0.5 text-[11px] font-medium tabular-nums text-op-ink dark:border-[#1E2433] dark:bg-[#151a26]">
                            {{ __(':done of :total done', ['done' => $doneCount, 'total' => $total]) }}
                        </p>
                    </div>

                    <div
                        class="mt-3 h-1.5 overflow-hidden rounded-[4px] bg-[#E4E5E9] dark:bg-[#1E2433]"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="{{ $total }}"
                        aria-valuenow="{{ $doneCount }}"
                        aria-label="{{ __('Setup progress') }}"
                    >
                        <div
                            class="h-full rounded-[4px] bg-[#FFEF4D] transition-[width] duration-300 ease-out"
                            style="width: {{ $percent }}%"
                        ></div>
                    </div>

                    @if ($next)
                        <div @class([
                            'mt-3 flex flex-col gap-3',
                            'sm:flex-row sm:items-center sm:justify-between' => $hasOtherSteps,
                        ])>
                            <x-button
                                :href="$next['url']"
                                size="sm"
                                wire:navigate
                                @class([
                                    'w-full justify-center',
                                    'sm:w-auto' => true,
                                ])
                            >
                                <span>{{ __('Continue') }}</span>
                                <span class="opacity-60" aria-hidden="true">·</span>
                                <span>{{ $next['label'] }}</span>
                                <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                            </x-button>

                            @if ($hasOtherSteps)
                                <p class="text-[11px] font-medium text-op-subtle sm:text-right">
                                    {{ __(':count more after this', ['count' => $otherRemaining->count()]) }}
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($hasOtherSteps)
                        <ul class="mt-3 flex flex-wrap gap-1.5 border-t border-op-line pt-3">
                            @foreach ($otherRemaining as $step)
                                <li>
                                    <a
                                        href="{{ $step['url'] }}"
                                        wire:navigate
                                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-[6px] border border-[#E4E5E9] bg-white px-2.5 py-1 text-[11px] font-medium text-op-ink transition hover:border-[#D1D3D8] hover:bg-[#F7F8F9] focus:outline-none focus:ring-1 focus:ring-[#FFEF4D] dark:border-[#1E2433] dark:bg-[#151a26] dark:hover:border-[#2A3245] dark:hover:bg-[#1A2030]"
                                    >
                                        <span>{{ $step['label'] }}</span>
                                        <i class="fa-solid fa-arrow-right text-[9px] text-op-subtle" aria-hidden="true"></i>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
