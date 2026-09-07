@php
    $operator = auth()->user()?->currentOperator();
    $currentPlan = $operator?->getPlan();
@endphp

<div class="space-y-4">
    <x-filter-tabs padded>
        <x-filter-tab :href="route('settings.plan')" icon="fa-crown" :active="request()->routeIs('settings.plan', 'settings.plan.checkout')">
            {{ __('Subscription & Plan') }}
            @if ($currentPlan)
                <span @class([
                    'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                    'bg-stone-900 text-amber-200' => request()->routeIs('settings.plan', 'settings.plan.checkout'),
                    'bg-op-muted text-op-subtle' => ! request()->routeIs('settings.plan', 'settings.plan.checkout'),
                ])>
                    {{ $currentPlan->name }}
                </span>
            @endif
        </x-filter-tab>
        <x-filter-tab :href="route('settings.billing')" icon="fa-file-invoice-dollar" :active="request()->routeIs('settings.billing')">
            {{ __('Billing & Invoices') }}
        </x-filter-tab>
        <x-filter-tab :href="route('payments.edit')" icon="fa-building-columns" :active="request()->routeIs('payments.edit')">
            {{ __('Payout bank account') }}
        </x-filter-tab>
    </x-filter-tabs>
</div>
