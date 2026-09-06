@props([
    'href',
    'icon' => null,
    'active' => false,
    'badge' => null,
    'badgeTone' => 'neutral',
])

<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->class('op-nav-item relative flex h-10 items-center rounded-xl px-2.5 text-sm font-semibold shadow-none') }}
    @if ($active) aria-current="page" @endif
>
    <div class="flex min-w-0 flex-1 items-center gap-2.5">
        @if ($icon)
            <span class="op-nav-icon shrink-0" aria-hidden="true">
                <i class="fa-solid {{ $icon }}"></i>
            </span>
        @endif
        <span class="truncate">{{ $slot }}</span>
    </div>

    @if (filled($badge) && $badge !== 0 && $badge !== '0')
        <span
            @class(['op-nav-badge', 'op-nav-badge-success' => $badgeTone === 'success'])
            aria-label="{{ is_numeric($badge) ? trans_choice(':count item|:count items', (int) $badge, ['count' => $badge]) : $badge }}"
        >
            {{ $badge }}
        </span>
    @endif

    @isset($meta)
        {{ $meta }}
    @endisset
</a>
