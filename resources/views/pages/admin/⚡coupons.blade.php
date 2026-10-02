<?php

use App\Concerns\RecordsAdminActions;
use App\Models\Operator;
use App\Models\PlatformAnnouncement;
use App\Models\PlatformCoupon;
use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Coupons')] #[Layout('layouts.admin')] class extends Component {
    use RecordsAdminActions;

    public bool $show_modal = false;
    public ?string $editing_id = null;

    // Usage report modal state
    public bool $show_usage_modal = false;
    public ?string $usage_report_coupon_id = null;

    // Confirmation modal states
    public ?string $confirming_delete_id = null;
    public ?string $confirming_delete_code = null;
    public ?string $confirming_toggle_id = null;
    public ?string $confirming_toggle_code = null;
    public bool $confirming_toggle_current_state = false;

    public ?string $operator_id = null;
    public string $redemption_scope = 'unlimited'; // unlimited, first_purchase_only, once_per_period
    public string $eligibility_rule_type = ''; // '' = manual, min_monthly_transactions, min_monthly_revenue, subscription_age_months
    public ?int $eligibility_threshold = null;
    public bool $announce_on_save = false;
    public string $code = '';
    public string $description = '';
    public string $discount_type = 'percentage'; // 'percentage' or 'fixed'
    public float $discount_value = 10.0;
    public float $min_spend = 0.0;
    public ?float $max_discount_amount = null;
    public ?int $max_uses = null;
    public bool $is_active = true;
    public ?string $starts_at = null;
    public ?string $expires_at = null;

    public string $search = '';
    public string $status_filter = 'all'; // 'all', 'active', 'expired'

    public function openCreateModal(): void
    {
        $this->editing_id = null;
        $this->operator_id = null;
        $this->redemption_scope = 'unlimited';
        $this->eligibility_rule_type = '';
        $this->eligibility_threshold = null;
        $this->announce_on_save = false;
        $this->code = '';
        $this->description = '';
        $this->discount_type = 'percentage';
        $this->discount_value = 10.0;
        $this->min_spend = 0.0;
        $this->max_discount_amount = null;
        $this->max_uses = null;
        $this->is_active = true;
        $this->starts_at = now()->format('Y-m-d\TH:i');
        $this->expires_at = now()->addMonths(1)->format('Y-m-d\TH:i');
        $this->show_modal = true;
    }

    public function editCoupon(string $id): void
    {
        $coupon = PlatformCoupon::forSubscription()->find($id);

        if (! $coupon) {
            return;
        }

        $this->editing_id = $coupon->id;
        $this->operator_id = $coupon->operator_id;
        $this->redemption_scope = $coupon->redemption_scope ?? 'unlimited';
        $rule = $coupon->eligibility_rule;
        $this->eligibility_rule_type = $rule['type'] ?? '';
        $this->eligibility_threshold = isset($rule['threshold']) ? (int) $rule['threshold'] : null;
        $this->announce_on_save = false;
        $this->code = $coupon->code;
        $this->description = (string) ($coupon->description ?? '');
        $this->discount_type = $coupon->discount_type;
        $this->discount_value = (float) $coupon->discount_value;
        $this->min_spend = (float) $coupon->min_spend;
        $this->max_discount_amount = $coupon->max_discount_amount !== null ? (float) $coupon->max_discount_amount : null;
        $this->max_uses = $coupon->max_uses;
        $this->is_active = $coupon->is_active;
        $this->starts_at = $coupon->starts_at?->format('Y-m-d\TH:i');
        $this->expires_at = $coupon->expires_at?->format('Y-m-d\TH:i');
        $this->show_modal = true;
    }

    public function closeModal(): void
    {
        $this->show_modal = false;
        $this->editing_id = null;
        $this->operator_id = null;
        $this->redemption_scope = 'unlimited';
        $this->eligibility_rule_type = '';
        $this->eligibility_threshold = null;
        $this->announce_on_save = false;
    }

    public function saveCoupon(): void
    {
        $cleanCode = strtoupper(trim($this->code));

        $this->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_num', \Illuminate\Validation\Rule::unique('platform_coupons', 'code')->where('scope', 'subscription')->ignore($this->editing_id)],
            'operator_id' => ['nullable', 'string', 'exists:operators,id'],
            'redemption_scope' => ['required', 'in:unlimited,first_purchase_only,once_per_period'],
            'eligibility_rule_type' => ['nullable', 'string', 'in:,min_monthly_transactions,min_monthly_revenue,subscription_age_months'],
            'eligibility_threshold' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'min_spend' => ['required', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $eligibilityRule = $this->eligibility_rule_type
            ? ['type' => $this->eligibility_rule_type, 'threshold' => $this->eligibility_threshold ?? 1, 'lookback_months' => 1]
            : null;

        $attributes = [
            'code' => $cleanCode,
            'description' => $this->description ?: null,
            'scope' => 'subscription',
            'redemption_scope' => $this->redemption_scope,
            'eligibility_rule' => $eligibilityRule,
            'operator_id' => $this->operator_id ?: null,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'min_spend' => $this->min_spend,
            'max_discount_amount' => $this->max_discount_amount ?: null,
            'max_uses' => $this->max_uses ?: null,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at ? Carbon::parse($this->starts_at) : null,
            'expires_at' => $this->expires_at ? Carbon::parse($this->expires_at) : null,
        ];

        if ($this->editing_id) {
            PlatformCoupon::forSubscription()->where('id', $this->editing_id)->update($attributes);
            $this->audit('coupon.updated', PlatformCoupon::find($this->editing_id), ['code' => $cleanCode]);
            session()->flash('success', __('Subscription coupon :code updated successfully!', ['code' => $cleanCode]));
        } else {
            $coupon = PlatformCoupon::create($attributes);
            $this->audit('coupon.created', $coupon, ['code' => $cleanCode]);

            // Optionally create a linked platform announcement
            if ($this->announce_on_save) {
                $discountLabel = $this->discount_type === 'percentage'
                    ? "{$this->discount_value}% OFF"
                    : 'Rp ' . number_format($this->discount_value, 0, ',', '.') . ' OFF';

                $announcement = PlatformAnnouncement::create([
                    'title' => __('New promo code available: :code', ['code' => $cleanCode]),
                    'message' => "Use code **{$cleanCode}** to get **{$discountLabel}** on your subscription checkout!" . ($this->expires_at ? ' Valid until ' . Carbon::parse($this->expires_at)->format('d M Y') . '.' : ''),
                    'type' => 'success',
                    'is_active' => true,
                    'is_dismissible' => true,
                    'starts_at' => $this->starts_at ? Carbon::parse($this->starts_at) : now(),
                    'ends_at' => $this->expires_at ? Carbon::parse($this->expires_at) : null,
                ]);

                $coupon->update(['announcement_id' => $announcement->id]);
            }

            session()->flash('success', __('New subscription coupon :code created and activated!', ['code' => $cleanCode]));
        }

        $this->closeModal();
    }

    public function promptToggleActive(string $id, string $code, bool $currentActive): void
    {
        $this->confirming_toggle_id = $id;
        $this->confirming_toggle_code = $code;
        $this->confirming_toggle_current_state = $currentActive;
    }

    public function cancelToggleActive(): void
    {
        $this->confirming_toggle_id = null;
        $this->confirming_toggle_code = null;
    }

    public function confirmToggleActive(): void
    {
        if ($this->confirming_toggle_id) {
            $this->toggleActive($this->confirming_toggle_id);
            $this->cancelToggleActive();
        }
    }

    public function toggleActive(string $id): void
    {
        $coupon = PlatformCoupon::forSubscription()->find($id);
        if ($coupon) {
            $coupon->update(['is_active' => ! $coupon->is_active]);
            $this->audit('coupon.toggled', $coupon, ['is_active' => $coupon->is_active]);
            session()->flash('success', __('Coupon status updated.'));
        }
    }

    public function promptDelete(string $id, string $code): void
    {
        $this->confirming_delete_id = $id;
        $this->confirming_delete_code = $code;
    }

    public function cancelDelete(): void
    {
        $this->confirming_delete_id = null;
        $this->confirming_delete_code = null;
    }

    public function confirmDelete(): void
    {
        if ($this->confirming_delete_id) {
            $this->deleteCoupon($this->confirming_delete_id);
            $this->cancelDelete();
        }
    }

    public function deleteCoupon(string $id): void
    {
        $this->audit('coupon.deleted', null, ['coupon_id' => $id, 'code' => PlatformCoupon::find($id)?->code]);
        PlatformCoupon::forSubscription()->where('id', $id)->delete();
        session()->flash('success', __('Coupon deleted successfully.'));
    }

    public function viewUsageReport(string $id): void
    {
        $this->usage_report_coupon_id = $id;
        $this->show_usage_modal = true;
    }

    public function closeUsageReport(): void
    {
        $this->show_usage_modal = false;
        $this->usage_report_coupon_id = null;
    }

    public function render()
    {
        $query = PlatformCoupon::forSubscription()->with(['operator.users'])->latest('created_at');

        if (! empty($this->search)) {
            $s = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', $s)
                    ->orWhere('description', 'like', $s)
                    ->orWhereHas('operator', function ($opQ) use ($s) {
                        $opQ->where('name', 'like', $s);
                    });
            });
        }

        if ($this->status_filter === 'active') {
            $query->active();
        } elseif ($this->status_filter === 'expired') {
            $query->where('expires_at', '<', now());
        }

        $coupons = $query->get();

        $activeCount = PlatformCoupon::forSubscription()->active()->count();
        $totalRedemptions = PlatformCoupon::forSubscription()->sum('used_count');

        $operators = Operator::with('users')->orderBy('name')->get();
        $operatorOptions = [
            ['value' => '', 'label' => __('Eligible for Any Operator (Global Promo)'), 'icon' => 'fa-solid fa-globe'],
        ];
        foreach ($operators as $op) {
            $primaryEmail = $op->users->first()?->email;
            $emailSuffix = $primaryEmail ? " ({$primaryEmail})" : '';
            $operatorOptions[] = [
                'value' => $op->id,
                'label' => "{$op->name}{$emailSuffix}",
                'icon' => 'fa-solid fa-building',
            ];
        }

        $usageCoupon = null;
        $usagePayments = collect();
        $usageTotalDiscount = 0;
        $usageTotalRevenue = 0;

        if ($this->show_usage_modal && $this->usage_report_coupon_id) {
            $usageCoupon = PlatformCoupon::forSubscription()->with('operator')->find($this->usage_report_coupon_id);
            if ($usageCoupon) {
                $usagePayments = SubscriptionPayment::with(['operator', 'plan'])
                    ->where('breakdown->coupon_code', $usageCoupon->code)
                    ->latest('created_at')
                    ->get();
                $usage = app(\App\Services\AdminMetricsService::class)->subscriptionCouponUsage($usageCoupon);
                $usageTotalDiscount = $usage['discount'];
                $usageTotalRevenue = $usage['revenue'];
            }
        }

        return view('pages.admin.⚡coupons', [
            'coupons' => $coupons,
            'activeCount' => $activeCount,
            'totalRedemptions' => $totalRedemptions,
            'operatorOptions' => $operatorOptions,
            'usageCoupon' => $usageCoupon,
            'usagePayments' => $usagePayments,
            'usageTotalDiscount' => $usageTotalDiscount,
            'usageTotalRevenue' => $usageTotalRevenue,
        ]);
    }
}; ?>

<div class="space-y-6">
    <x-page-header
        :title="__('Coupons')"
        :subtitle="__('Discount codes for operator plan bills.')"
        icon="fa-ticket"
    >
        <x-slot:actions>
            <button type="button" wire:click="openCreateModal"
                class="h-8 px-3 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-medium text-[13px] inline-flex items-center gap-1.5 shadow-none transition cursor-pointer">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>{{ __('New coupon') }}</span>
            </button>
        </x-slot:actions>
    </x-page-header>

    <!-- Feedback Alerts -->
    @if (session()->has('success'))
        <div class="p-3.5 rounded-[12px] bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0] text-[13px] font-medium flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-sm text-[#059669]"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-metric-card
            :label="__('Active Promo Codes')"
            :value="$activeCount"
            :hint="__('Available for customer checkout')"
            icon="fa-ticket"
            tone="featured"
        />
        <x-metric-card
            :label="__('Total Redemptions')"
            :value="$totalRedemptions"
            :hint="__('Coupons claimed by guests')"
            icon="fa-receipt"
        />
        <x-metric-card
            :label="__('Engine Status')"
            :value="__('Operational')"
            :hint="__('Platform subscription discount rules')"
            icon="fa-circle-check"
            tone="success"
        />
    </div>

    <x-toolbar class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-search-input
            class="w-full sm:w-80"
            wire:model.live.debounce.300ms="search"
            :placeholder="__('Search code or description...')"
        />

        <x-filter-tabs>
            <x-filter-tab wire:click="$set('status_filter', 'all')" :active="$status_filter === 'all'">{{ __('All') }}</x-filter-tab>
            <x-filter-tab wire:click="$set('status_filter', 'active')" :active="$status_filter === 'active'">
                {{ __('Active (:count)', ['count' => $activeCount]) }}
            </x-filter-tab>
            <x-filter-tab wire:click="$set('status_filter', 'expired')" :active="$status_filter === 'expired'">
                {{ __('Expired / Ended') }}
            </x-filter-tab>
        </x-filter-tabs>
    </x-toolbar>

    <!-- Coupons Table -->
    <div class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <thead>
                    <tr class="bg-[#FAFAFB] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-medium uppercase tracking-wider text-[#60646C]">
                        <th class="py-3 px-4 sm:px-5">{{ __('Promo Code') }}</th>
                        <th class="py-3 px-4">{{ __('Target Eligibility') }}</th>
                        <th class="py-3 px-4">{{ __('Discount') }}</th>
                        <th class="py-3 px-4">{{ __('Redemptions / Limits') }}</th>
                        <th class="py-3 px-4">{{ __('Validity Window') }}</th>
                        <th class="py-3 px-4 sm:px-5 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                    @forelse ($coupons as $coupon)
                        @php
                            $isExpired = $coupon->expires_at && $coupon->expires_at->isPast();
                            $isLimitReached = $coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses;
                        @endphp
                        <tr class="hover:bg-[#FAFAFB] dark:hover:bg-[#141821]/60 transition group">
                            <!-- Code -->
                            <td class="py-3.5 px-4 sm:px-5">
                                <div class="space-y-1">
                                    <span class="inline-flex items-center gap-1.5 font-mono font-medium text-[11px] text-[#856404] dark:text-[#FFEF4D] px-2 py-0.5 rounded-[6px] bg-[#FFEF4D]/20 border border-[#FFEF4D]/40">
                                        <i class="fa-solid fa-tag text-[9px]"></i>
                                        {{ $coupon->code }}
                                    </span>
                                    @if ($coupon->description)
                                        <p class="text-[12px] text-[#60646C] dark:text-slate-400 truncate max-w-xs">{{ $coupon->description }}</p>
                                    @endif
                                    {{-- Redemption scope badge --}}
                                    @php
                                        $scopeBadge = match($coupon->redemption_scope ?? 'unlimited') {
                                            'first_purchase_only' => ['label' => '1st Only', 'class' => 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]'],
                                            'once_per_period' => ['label' => 'Per Cycle', 'class' => 'bg-[#EFF6FF] text-[#1E40AF] border border-[#BFDBFE]'],
                                            default => ['label' => __('Lifetime'), 'class' => 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]'],
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-[6px] text-[10px] font-medium {{ $scopeBadge['class'] }}">
                                        {{ $scopeBadge['label'] }}
                                    </span>
                                    @if ($coupon->eligibility_rule)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-[6px] text-[10px] font-medium bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 ml-1" title="{{ __('Auto-broadcast enabled') }}">
                                            <i class="fa-solid fa-satellite-dish text-[8px]"></i>
                                            {{ $coupon->eligibility_rule['threshold'] ?? '?' }}
                                            {{ $coupon->eligibility_rule['type'] === 'min_monthly_transactions' ? 'txn/mo' : ($coupon->eligibility_rule['type'] === 'subscription_age_months' ? 'mo sub' : 'Rp/mo') }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Target Eligibility -->
                            <td class="py-3.5 px-4">
                                @if ($coupon->operator)
                                    <div class="space-y-0.5">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] text-[12px] font-medium bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                                            <i class="fa-solid fa-building text-[10px]"></i>
                                            <span class="truncate max-w-[150px]">{{ $coupon->operator->name }}</span>
                                        </span>
                                        <p class="text-[11px] text-[#8B8D98] truncate max-w-[160px]">
                                            {{ $coupon->operator->users->first()?->email }}
                                        </p>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] text-[12px] font-medium bg-white dark:bg-[#141821] text-[#1C2024] dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433]">
                                        <i class="fa-solid fa-globe text-[10px] text-[#8B8D98]"></i>
                                        <span>{{ __('Global (All Operators)') }}</span>
                                    </span>
                                @endif
                            </td>

                            <!-- Discount Value -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-0.5">
                                    <div class="font-semibold text-[#1C2024] dark:text-white text-[13px]">
                                        @if ($coupon->discount_type === 'percentage')
                                            {{ (float) $coupon->discount_value }}% {{ __('OFF') }}
                                        @else
                                            Rp {{ number_format((float) $coupon->discount_value, 0, ',', '.') }} {{ __('OFF') }}
                                        @endif
                                    </div>
                                    <div class="text-[12px] text-[#60646C] dark:text-slate-400">
                                        @if ($coupon->min_spend > 0)
                                            {{ __('Min:') }} Rp {{ number_format((float) $coupon->min_spend, 0, ',', '.') }}
                                        @else
                                            {{ __('No minimum') }}
                                        @endif
                                        @if ($coupon->max_discount_amount)
                                            &bull; {{ __('Cap:') }} Rp {{ number_format((float) $coupon->max_discount_amount, 0, ',', '.') }}
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Redemptions / Limits -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-0.5 font-mono text-[12px]">
                                    <span class="font-medium text-[#1C2024] dark:text-white">
                                        <button
                                            type="button"
                                            wire:click="viewUsageReport('{{ $coupon->id }}')"
                                            class="hover:underline text-[#856404] dark:text-[#FFEF4D] font-medium transition inline-flex items-center gap-1 cursor-pointer"
                                            title="{{ __('View redemptions report') }}"
                                        >
                                            <span>{{ $coupon->used_count }}</span>
                                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px] opacity-70"></i>
                                        </button>
                                        <span class="text-[#8B8D98] font-normal">/ {{ $coupon->max_uses ? $coupon->max_uses . ' max' : '∞' }}</span>
                                    </span>
                                    @if ($isLimitReached)
                                        <span class="block text-[11px] font-medium text-rose-500 uppercase">{{ __('Limit Reached') }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Validity Window & Status -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-1">
                                    <button
                                        type="button"
                                        wire:click="promptToggleActive('{{ $coupon->id }}', '{{ $coupon->code }}', {{ $coupon->is_active && ! $isExpired ? 'true' : 'false' }})"
                                        class="h-6 px-2.5 rounded-[6px] inline-flex items-center gap-1.5 text-[11px] font-medium uppercase transition cursor-pointer {{ $coupon->is_active && ! $isExpired ? 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]' : 'bg-[#FAFAFB] text-[#60646C] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]' }}"
                                        title="{{ __('Click to change active status') }}"
                                    >
                                        <i class="fa-solid {{ $coupon->is_active && ! $isExpired ? 'fa-check' : 'fa-xmark' }} text-[9px]"></i>
                                        <span>{{ $coupon->is_active && ! $isExpired ? __('Active') : ($isExpired ? __('Expired') : __('Disabled')) }}</span>
                                    </button>

                                    @if ($coupon->expires_at)
                                        <p class="font-mono text-[11px] text-[#60646C] dark:text-slate-400">
                                            {{ __('Expires:') }} {{ $coupon->expires_at->format('d M Y') }}
                                        </p>
                                    @endif
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 sm:px-5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        wire:click="viewUsageReport('{{ $coupon->id }}')"
                                        class="h-7 px-2.5 rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] text-[#1C2024] dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-medium transition inline-flex items-center gap-1 cursor-pointer shadow-none"
                                        title="{{ __('View Usage Report') }}"
                                    >
                                        <i class="fa-solid fa-chart-pie text-[10px]"></i>
                                        <span>{{ __('Report') }}</span>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="editCoupon('{{ $coupon->id }}')"
                                        class="h-7 px-2.5 rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] text-[#1C2024] dark:text-slate-300 border border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-medium transition inline-flex items-center gap-1 cursor-pointer shadow-none"
                                        title="{{ __('Edit Coupon') }}"
                                    >
                                        <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                                        <span>{{ __('Edit') }}</span>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="promptDelete('{{ $coupon->id }}', '{{ $coupon->code }}')"
                                        class="h-7 w-7 rounded-[6px] bg-[#FEF2F2] hover:bg-[#FEE2E2] text-[#991B1B] border border-[#FECACA] text-[11px] transition inline-flex items-center justify-center cursor-pointer shadow-none"
                                        title="{{ __('Delete Coupon') }}"
                                    >
                                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-[#8B8D98]">
                                <i class="fa-solid fa-ticket text-2xl mb-1.5 block opacity-40"></i>
                                <span class="font-medium text-[13px] text-[#60646C] dark:text-slate-300 block">{{ __('No coupon codes found') }}</span>
                                <p class="text-[12px] text-[#8B8D98] mt-0.5">{{ __('No subscription promo coupons match your search or filter.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    @if ($confirming_delete_id)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-[#12181E]/60 backdrop-blur-xs">
                <div class="w-full max-w-md rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none p-5 space-y-4 text-center">
                    <div class="w-10 h-10 rounded-[8px] bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA] flex items-center justify-center mx-auto text-base">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>

                    <div class="space-y-1">
                        <h3 class="text-[15px] font-semibold text-[#1C2024] dark:text-white">
                            {{ __('Delete Promo Code ":code"?', ['code' => $confirming_delete_code]) }}
                        </h3>
                        <p class="text-[12px] text-[#60646C] dark:text-slate-400 max-w-xs mx-auto leading-relaxed">
                            {{ __('Are you sure you want to delete promo code :code? This action cannot be undone.', ['code' => $confirming_delete_code]) }}
                        </p>
                    </div>

                    <div class="flex items-center justify-center gap-2.5 pt-2">
                        <button
                            type="button"
                            wire:click="cancelDelete"
                            class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-white transition cursor-pointer shadow-none"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            wire:click="confirmDelete"
                            class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium bg-[#FEF2F2] hover:bg-[#FEE2E2] text-[#991B1B] border border-[#FECACA] inline-flex items-center gap-1.5 transition cursor-pointer shadow-none"
                        >
                            <i class="fa-solid fa-trash-can text-xs"></i>
                            <span>{{ __('Yes, Delete Promo Code') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Toggle Active Status Confirmation Modal -->
    @if ($confirming_toggle_id)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-[#12181E]/60 backdrop-blur-xs">
                <div class="w-full max-w-md rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none p-5 space-y-4 text-center">
                    <div class="w-10 h-10 rounded-[8px] {{ $confirming_toggle_current_state ? 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]' : 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]' }} flex items-center justify-center mx-auto text-base">
                        <i class="fa-solid {{ $confirming_toggle_current_state ? 'fa-pause' : 'fa-play' }}"></i>
                    </div>

                    <div class="space-y-1">
                        <h3 class="text-[15px] font-semibold text-[#1C2024] dark:text-white">
                            {{ $confirming_toggle_current_state ? __('Deactivate Promo Code ":code"?', ['code' => $confirming_toggle_code]) : __('Activate Promo Code ":code"?', ['code' => $confirming_toggle_code]) }}
                        </h3>
                        <p class="text-[12px] text-[#60646C] dark:text-slate-400 max-w-xs mx-auto leading-relaxed">
                            {{ $confirming_toggle_current_state ? __('Deactivating this promo code will prevent operators from applying it during checkout.') : __('Activating this promo code will allow operators to immediately apply it during subscription checkout.') }}
                        </p>
                    </div>

                    <div class="flex items-center justify-center gap-2.5 pt-2">
                        <button
                            type="button"
                            wire:click="cancelToggleActive"
                            class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-white transition cursor-pointer shadow-none"
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            wire:click="confirmToggleActive"
                            class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium inline-flex items-center gap-1.5 transition cursor-pointer shadow-none {{ $confirming_toggle_current_state ? 'text-[#92400E] bg-[#FFFBEB] hover:bg-[#FEF3C7] border border-[#FDE68A]' : 'bg-[#ECFDF5] hover:bg-[#D1FAE5] text-[#065F46] border border-[#A7F3D0]' }}"
                        >
                            <i class="fa-solid {{ $confirming_toggle_current_state ? 'fa-circle-pause' : 'fa-circle-check' }} text-xs"></i>
                            <span>{{ $confirming_toggle_current_state ? __('Deactivate Code') : __('Activate Code') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Create / Edit Modal -->
    @if ($show_modal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-[#12181E]/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-lg rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none flex flex-col my-8 overflow-hidden">
                    <!-- Modal Header -->
                    <div class="p-4 sm:p-5 border-b border-[#E4E5E9] dark:border-[#1E2433] flex items-start justify-between gap-3 bg-[#FAFAFB] dark:bg-[#141821]">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-[8px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-sm shadow-none shrink-0 mt-0.5 font-semibold">
                                <i class="fa-solid fa-ticket"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-semibold text-[16px] text-[#1C2024] dark:text-white leading-tight truncate">
                                    {{ $editing_id ? __('Edit Subscription Promo Code') : __('Create Subscription Promo Code') }}
                                </h3>
                                <p class="text-[12px] text-[#60646C] dark:text-slate-400">
                                    {{ __('Configure SaaS subscription discounts and targeted operator eligibility.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeModal" class="p-1.5 rounded-[6px] text-[#8B8D98] hover:text-[#1C2024] dark:hover:text-white transition cursor-pointer shrink-0">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form wire:submit="saveCoupon" class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="code" :value="__('Coupon Code (e.g. GROW2026)')" required />
                                <x-input id="code" type="text" wire:model="code" placeholder="{{ __('GROW2026') }}" class="font-mono uppercase font-semibold" :error="$errors->has('code')" />
                                <x-input-error :messages="$errors->get('code')" />
                            </div>

                            <div>
                                <x-label for="discount_type" :value="__('Discount Format')" required />
                                <x-select
                                    id="discount_type"
                                    wire:model.live="discount_type"
                                    :options="[
                                        ['value' => 'percentage', 'label' => __('Percentage (% OFF)')],
                                        ['value' => 'fixed', 'label' => __('Fixed Amount (Rp OFF)')],
                                    ]"
                                    :error="$errors->has('discount_type')"
                                />
                                <x-input-error :messages="$errors->get('discount_type')" />
                            </div>
                        </div>

                        <div>
                            <x-label for="operator_id" :value="__('Target Operator Eligibility')" />
                            <x-select
                                id="operator_id"
                                wire:model="operator_id"
                                :options="$operatorOptions"
                                :searchable="true"
                                :error="$errors->has('operator_id')"
                            />
                            <p class="text-[11px] text-[#8B8D98] mt-1">
                                {{ __('Target a specific operator exclusively or keep Global for all subscribing operators.') }}
                            </p>
                            <x-input-error :messages="$errors->get('operator_id')" />
                        </div>

                        {{-- Redemption Scope --}}
                        <div class="pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                            <x-label for="redemption_scope" :value="__('How Many Times Can This Be Redeemed?')" required />
                            <x-select
                                id="redemption_scope"
                                wire:model="redemption_scope"
                                :options="[
                                    ['value' => 'unlimited', 'label' => __('Unlimited — Every checkout & renewal (Lifetime Deal)'), 'icon' => 'fa-solid fa-infinity'],
                                    ['value' => 'first_purchase_only', 'label' => __('First Subscription Only — New operator onboarding discount'), 'icon' => 'fa-solid fa-user-plus'],
                                    ['value' => 'once_per_period', 'label' => __('Once Per Billing Cycle — Monthly / Annual renewal discount'), 'icon' => 'fa-solid fa-calendar-day'],
                                ]"
                                :error="$errors->has('redemption_scope')"
                            />
                            <p class="text-[11px] text-[#8B8D98] mt-1">
                                @if ($redemption_scope === 'unlimited')
                                    {{ __('Operator keeps this discount on every subscription renewal as long as the promo is active.') }}
                                @elseif ($redemption_scope === 'first_purchase_only')
                                    {{ __('Operator can only use this once — their very first subscription checkout.') }}
                                @else
                                    {{ __('Operator can use this once per billing cycle (month or year).') }}
                                @endif
                            </p>
                            <x-input-error :messages="$errors->get('redemption_scope')" />
                        </div>

                        {{-- Broadcast Eligibility Rule --}}
                        <div>
                            <x-label for="eligibility_rule_type" :value="__('Auto-Broadcast Eligibility Rule')" />
                            <x-select
                                id="eligibility_rule_type"
                                wire:model.live="eligibility_rule_type"
                                :options="[
                                    ['value' => '', 'label' => __('Manual Only — Admin broadcasts manually'), 'icon' => 'fa-solid fa-hand'],
                                    ['value' => 'min_monthly_transactions', 'label' => __('Min. Monthly Confirmed Bookings (volume milestone)'), 'icon' => 'fa-solid fa-chart-column'],
                                    ['value' => 'min_monthly_revenue', 'label' => __('Min. guest payments this month'), 'icon' => 'fa-solid fa-sack-dollar'],
                                    ['value' => 'subscription_age_months', 'label' => __('Subscription Age (loyalty reward)'), 'icon' => 'fa-solid fa-heart'],
                                ]"
                                :error="$errors->has('eligibility_rule_type')"
                            />
                            <p class="text-[11px] text-[#8B8D98] mt-1">
                                {{ __('The daily coupons:broadcast job will auto-notify qualifying operators via a dashboard announcement.') }}
                            </p>

                            @if ($eligibility_rule_type)
                                <div class="mt-2">
                                    <x-label for="eligibility_threshold" :value="
                                        match($eligibility_rule_type) {
                                            'min_monthly_transactions' => __('Threshold: Minimum confirmed bookings/month'),
                                            'min_monthly_revenue' => __('Threshold: Minimum revenue (Rp) in last 30 days'),
                                            'subscription_age_months' => __('Threshold: Minimum subscription age (months)'),
                                            default => __('Threshold'),
                                        }
                                    " required />
                                    <x-input
                                        id="eligibility_threshold"
                                        type="number"
                                        step="1"
                                        wire:model="eligibility_threshold"
                                        class="font-medium font-mono"
                                        placeholder="{{ $eligibility_rule_type === 'min_monthly_transactions' ? '50' : ($eligibility_rule_type === 'subscription_age_months' ? '6' : '5000000') }}"
                                        :error="$errors->has('eligibility_threshold')"
                                    />
                                    <x-input-error :messages="$errors->get('eligibility_threshold')" />
                                </div>
                            @endif
                        </div>

                        <div>
                            <x-label for="description" :value="__('Campaign Description')" />
                            <x-input id="description" type="text" wire:model="description" placeholder="{{ __('e.g. Special VIP upgrade incentive') }}" :error="$errors->has('description')" />
                            <x-input-error :messages="$errors->get('description')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                            <div>
                                <x-label for="discount_value" :value="$discount_type === 'percentage' ? __('Discount (%)') : __('Discount (Rp)')" required />
                                <x-input id="discount_value" type="number" step="0.1" wire:model="discount_value" class="font-medium font-mono" :error="$errors->has('discount_value')" />
                                <x-input-error :messages="$errors->get('discount_value')" />
                            </div>

                            <div>
                                <x-label for="min_spend" :value="__('Min Spend (Rp)')" required />
                                <x-input id="min_spend" type="number" step="1000" wire:model="min_spend" class="font-medium" :error="$errors->has('min_spend')" />
                                <x-input-error :messages="$errors->get('min_spend')" />
                            </div>

                            <div>
                                <x-label for="max_discount_amount" :value="__('Max Cap (Rp)')" />
                                <x-input id="max_discount_amount" type="number" step="1000" wire:model="max_discount_amount" placeholder="{{ __('No Cap') }}" :error="$errors->has('max_discount_amount')" />
                                <x-input-error :messages="$errors->get('max_discount_amount')" />
                            </div>
                        </div>

                        <div>
                            <x-label for="max_uses" :value="__('Total Max Uses (Blank = Unlimited)')" />
                            <x-input id="max_uses" type="number" wire:model="max_uses" placeholder="{{ __('Unlimited') }}" :error="$errors->has('max_uses')" />
                            <x-input-error :messages="$errors->get('max_uses')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="starts_at" :value="__('Active From')" />
                                <x-datetime-picker
                                    id="starts_at"
                                    wire:model="starts_at"
                                    :error="$errors->has('starts_at')"
                                />
                                <x-input-error :messages="$errors->get('starts_at')" />
                            </div>

                            <div>
                                <x-label for="expires_at" :value="__('Expires At')" />
                                <x-datetime-picker
                                    id="expires_at"
                                    wire:model="expires_at"
                                    :error="$errors->has('expires_at')"
                                />
                                <x-input-error :messages="$errors->get('expires_at')" />
                            </div>
                        </div>

                        <div class="pt-3 border-t border-[#E4E5E9] dark:border-[#1E2433] space-y-2">
                            <div class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                <x-checkbox
                                    id="coupon_is_active"
                                    wire:model="is_active"
                                    :label="__('Enable promo code immediately (Active)')"
                                    :description="__('Operators will be able to apply this promo code during subscription checkout / upgrade right away')"
                                />
                            </div>

                            @if (! $editing_id)
                                <div class="p-2.5 rounded-[8px] border border-[#FFEF4D]/40 bg-[#FFEF4D]/10 transition">
                                    <x-checkbox
                                        id="coupon_announce_on_save"
                                        wire:model="announce_on_save"
                                        :label="__('Publish announcement to operator dashboard')"
                                        :description="__('Creates a dismissible success announcement banner visible to all operators (or plan-targeted if applicable) when this coupon is saved.')"
                                    />
                                </div>
                            @endif
                        </div>

                        <!-- Modal Actions Footer -->
                        <div class="pt-4 border-t border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-end gap-2.5">
                            <button type="button" wire:click="closeModal" class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-white transition cursor-pointer shadow-none">
                                {{ __('Cancel') }}
                            </button>
                            <button
                                type="submit"
                                class="h-8 px-3.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-medium text-[13px] shadow-none transition inline-flex items-center gap-1.5 cursor-pointer"
                            >
                                <i class="fa-solid fa-floppy-disk text-xs"></i>
                                <span>{{ $editing_id ? __('Save Changes') : __('Create Promo Code') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Coupon Usage & Redemptions Report Modal -->
    @if ($show_usage_modal && $usageCoupon)
        @teleport('body')
            <div
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-[#12181E]/60 backdrop-blur-xs"
                wire:keydown.escape.window="closeUsageReport"
            >
                <div class="w-full max-w-4xl max-h-[90vh] flex flex-col rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden">
                    <!-- Modal Header -->
                    <div class="px-5 py-4 border-b border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-between bg-[#FAFAFB] dark:bg-[#141821] shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-[8px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 flex items-center justify-center text-sm font-semibold">
                                <i class="fa-solid fa-chart-pie"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-[16px] font-semibold text-[#1C2024] dark:text-white">
                                        {{ __('Subscription Coupon Redemption Report') }}
                                    </h3>
                                    <span class="px-2 py-0.5 rounded-[6px] font-mono text-[11px] font-medium bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                                        {{ $usageCoupon->code }}
                                    </span>
                                </div>
                                <p class="text-[12px] text-[#60646C] dark:text-slate-400 mt-0.5">
                                    {{ $usageCoupon->description ?: __('Subscription checkout discount code usage & operator transactions') }}
                                    @if ($usageCoupon->operator)
                                        &bull; <span class="font-medium text-[#1C2024] dark:text-slate-300">{{ __('Operator:') }} {{ $usageCoupon->operator->name }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="closeUsageReport"
                            class="p-1.5 rounded-[6px] text-[#8B8D98] hover:text-[#1C2024] dark:hover:text-white transition cursor-pointer"
                        >
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body / Stats & Breakdown -->
                    <div class="p-5 space-y-5 overflow-y-auto">
                        <!-- KPI Metric Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="p-4 rounded-[8px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]">
                                <span class="text-[10px] font-medium uppercase tracking-wider text-[#8B8D98] block">{{ __('Total Redemptions') }}</span>
                                <div class="flex items-baseline gap-1.5 mt-1">
                                    <span class="text-[20px] font-semibold font-mono text-[#1C2024] dark:text-white">
                                        {{ $usageCoupon->used_count }}
                                    </span>
                                    <span class="text-[12px] text-[#8B8D98] font-mono">
                                        / {{ $usageCoupon->max_uses ? $usageCoupon->max_uses . ' ' . __('max') : '∞' }}
                                    </span>
                                </div>
                                <span class="text-[11px] text-[#60646C] mt-0.5 block">
                                    {{ $usageCoupon->is_active ? __('Promo code is currently active') : __('Promo code is currently inactive') }}
                                </span>
                            </div>

                            <div class="p-4 rounded-[8px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]">
                                <span class="text-[10px] font-medium uppercase tracking-wider text-[#8B8D98] block">{{ __('Total Discounts Given') }}</span>
                                <p class="text-[20px] font-semibold font-mono text-[#856404] dark:text-[#FFEF4D] mt-1">
                                    Rp {{ number_format($usageTotalDiscount, 0, ',', '.') }}
                                </p>
                                <span class="text-[11px] text-[#60646C] mt-0.5 block">{{ __('Savings provided to operators') }}</span>
                            </div>

                            <div class="p-4 rounded-[8px] bg-[#FAFAFB] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433]">
                                <span class="text-[10px] font-medium uppercase tracking-wider text-[#8B8D98] block">{{ __('Net Revenue Collected') }}</span>
                                <p class="text-[20px] font-semibold font-mono text-emerald-700 dark:text-emerald-400 mt-1">
                                    Rp {{ number_format($usageTotalRevenue, 0, ',', '.') }}
                                </p>
                                <span class="text-[11px] text-[#60646C] mt-0.5 block">{{ __('From paid subscription invoices') }}</span>
                            </div>
                        </div>

                        <!-- Redemptions List -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h4 class="text-[12px] font-medium uppercase tracking-wider text-[#60646C]">
                                    {{ __('Itemized Invoices with this Promo Code') }}
                                </h4>
                                <span class="text-[12px] text-[#8B8D98] font-mono">{{ $usagePayments->count() }} {{ __('records') }}</span>
                            </div>

                            <div class="rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] overflow-hidden max-h-72 overflow-y-auto">
                                <table class="w-full text-left text-[12px]">
                                    <thead class="bg-[#FAFAFB] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1E2433] text-[10px] font-medium uppercase text-[#60646C] sticky top-0 z-10">
                                        <tr>
                                            <th class="py-2.5 px-3.5">{{ __('Invoice #') }}</th>
                                            <th class="py-2.5 px-3">{{ __('Operator') }}</th>
                                            <th class="py-2.5 px-3">{{ __('Plan / Interval') }}</th>
                                            <th class="py-2.5 px-3">{{ __('Date') }}</th>
                                            <th class="py-2.5 px-3 text-right">{{ __('Discount') }}</th>
                                            <th class="py-2.5 px-3 text-right">{{ __('Net Paid') }}</th>
                                            <th class="py-2.5 px-3.5 text-center">{{ __('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                                        @forelse ($usagePayments as $pay)
                                            @php
                                                $b = $pay->breakdown ?? [];
                                                $discount = (float) ($b['discount_amount'] ?? 0);
                                                $net = (float) $pay->net_amount_paid;
                                            @endphp
                                            <tr class="hover:bg-[#FAFAFB] dark:hover:bg-[#141821]/50 transition">
                                                <td class="py-2.5 px-3.5 font-mono font-medium text-[#1C2024] dark:text-white">
                                                    {{ $pay->invoice_number }}
                                                </td>
                                                <td class="py-2.5 px-3">
                                                    <div class="font-medium text-[#1C2024] dark:text-slate-200 truncate max-w-[140px]">{{ $pay->operator?->name ?? '—' }}</div>
                                                    <div class="text-[10px] text-[#8B8D98] truncate max-w-[140px]">{{ $pay->operator?->slug ?? '' }}</div>
                                                </td>
                                                <td class="py-2.5 px-3 text-[#60646C]">
                                                    <span class="font-medium text-[#1C2024] dark:text-slate-200">{{ $pay->plan?->name ?? __('Plan') }}</span>
                                                    <span class="text-[10px] text-[#8B8D98] block capitalize">{{ $pay->billing_interval }}</span>
                                                </td>
                                                <td class="py-2.5 px-3 font-mono text-[#60646C] whitespace-nowrap">
                                                    {{ $pay->paid_at ? $pay->paid_at->format('d M Y, H:i') : $pay->created_at->format('d M Y, H:i') }}
                                                </td>
                                                <td class="py-2.5 px-3 text-right font-mono font-medium text-[#856404] dark:text-[#FFEF4D]">
                                                    - Rp {{ number_format($discount, 0, ',', '.') }}
                                                </td>
                                                <td class="py-2.5 px-3 text-right font-mono font-medium text-[#1C2024] dark:text-white">
                                                    Rp {{ number_format($net, 0, ',', '.') }}
                                                </td>
                                                <td class="py-2.5 px-3.5 text-center">
                                                    <span class="px-2 py-0.5 rounded-[6px] text-[10px] font-medium border
                                                        @if ($pay->status === 'paid' || $pay->status === 'completed') bg-[#ECFDF5] text-[#065F46] border-[#A7F3D0]
                                                        @elseif ($pay->status === 'pending') bg-[#FFFBEB] text-[#92400E] border-[#FDE68A]
                                                        @else bg-[#FEF2F2] text-[#991B1B] border-[#FECACA]
                                                        @endif">
                                                        {{ ucfirst($pay->status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="py-8 text-center text-[#8B8D98]">
                                                    <i class="fa-solid fa-receipt text-2xl mb-1.5 block opacity-40"></i>
                                                    <span class="text-[12px] font-medium text-[#60646C] dark:text-slate-400">{{ __('No operator subscriptions have redeemed this coupon yet.') }}</span>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-5 py-3 border-t border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821] flex items-center justify-end shrink-0">
                        <button type="button" wire:click="closeUsageReport" class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-white transition cursor-pointer shadow-none">
                            {{ __('Close Report') }}
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
