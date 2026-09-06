@props([
    'status',
    'size' => 'sm',
])

@php
    /**
     * Renders any domain status enum (reservation, payment, listing, payout, domain)
     * with one consistent colour language, so "confirmed" looks the same everywhere.
     */
    $value = $status instanceof BackedEnum ? $status->value : (string) $status;

    $label = ($status instanceof BackedEnum && method_exists($status, 'label'))
        ? $status->label()
        : Str::headline($value);

    $variant = match ($value) {
        'confirmed', 'paid', 'published', 'completed', 'approved', 'active', 'cleared' => 'success',
        'payment_pending', 'pending_confirmation', 'pending', 'processing', 'pending_escrow' => 'warning',
        'declined', 'cancelled', 'expired', 'failed', 'rejected', 'suspended' => 'danger',
        'draft', 'archived' => 'neutral',
        default => 'info',
    };

    $dotColor = match ($variant) {
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
        'neutral' => 'bg-slate-400',
        default => 'bg-sky-500',
    };
@endphp

<x-badge :variant="$variant" :size="$size" {{ $attributes->merge(['class' => 'gap-1.5 font-bold whitespace-nowrap']) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dotColor }}" aria-hidden="true"></span>
    {{ $label }}
</x-badge>
