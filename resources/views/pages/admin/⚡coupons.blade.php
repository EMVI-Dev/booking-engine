<?php

use App\Models\Operator;
use App\Models\PlatformCoupon;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Platform Coupons & Promo Codes')] #[Layout('layouts.admin')] class extends Component {
    public bool $show_modal = false;
    public ?string $editing_id = null;

    public string $code = '';
    public string $description = '';
    public string $discount_type = 'percentage'; // 'percentage' or 'fixed'
    public float $discount_value = 10.0;
    public float $min_spend = 0.0;
    public ?float $max_discount_amount = null;
    public ?string $operator_id = null;
    public ?int $max_uses = null;
    public bool $is_active = true;
    public ?string $starts_at = null;
    public ?string $expires_at = null;

    public string $search = '';
    public string $status_filter = 'all'; // 'all', 'active', 'expired'

    public function openCreateModal(): void
    {
        $this->editing_id = null;
        $this->code = '';
        $this->description = '';
        $this->discount_type = 'percentage';
        $this->discount_value = 10.0;
        $this->min_spend = 0.0;
        $this->max_discount_amount = null;
        $this->operator_id = null;
        $this->max_uses = null;
        $this->is_active = true;
        $this->starts_at = now()->format('Y-m-d\TH:i');
        $this->expires_at = now()->addMonths(1)->format('Y-m-d\TH:i');
        $this->show_modal = true;
    }

    public function editCoupon(string $id): void
    {
        $coupon = PlatformCoupon::find($id);

        if (! $coupon) {
            return;
        }

        $this->editing_id = $coupon->id;
        $this->code = $coupon->code;
        $this->description = (string) ($coupon->description ?? '');
        $this->discount_type = $coupon->discount_type;
        $this->discount_value = (float) $coupon->discount_value;
        $this->min_spend = (float) $coupon->min_spend;
        $this->max_discount_amount = $coupon->max_discount_amount !== null ? (float) $coupon->max_discount_amount : null;
        $this->operator_id = $coupon->operator_id;
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
    }

    public function saveCoupon(): void
    {
        $cleanCode = strtoupper(trim($this->code));

        $this->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_num', 'unique:platform_coupons,code,' . ($this->editing_id ?: 'NULL') . ',id'],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'min_spend' => ['required', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'operator_id' => ['nullable', 'exists:operators,id'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $attributes = [
            'code' => $cleanCode,
            'description' => $this->description ?: null,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'min_spend' => $this->min_spend,
            'max_discount_amount' => $this->max_discount_amount ?: null,
            'operator_id' => $this->operator_id ?: null,
            'max_uses' => $this->max_uses ?: null,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at ? Carbon::parse($this->starts_at) : null,
            'expires_at' => $this->expires_at ? Carbon::parse($this->expires_at) : null,
        ];

        if ($this->editing_id) {
            PlatformCoupon::where('id', $this->editing_id)->update($attributes);
            session()->flash('success', __('Coupon code :code updated successfully!', ['code' => $cleanCode]));
        } else {
            PlatformCoupon::create($attributes);
            session()->flash('success', __('New coupon code :code created and activated!', ['code' => $cleanCode]));
        }

        $this->closeModal();
    }

    public function toggleActive(string $id): void
    {
        $coupon = PlatformCoupon::find($id);
        if ($coupon) {
            $coupon->update(['is_active' => ! $coupon->is_active]);
            session()->flash('success', __('Coupon status updated.'));
        }
    }

    public function deleteCoupon(string $id): void
    {
        PlatformCoupon::where('id', $id)->delete();
        session()->flash('success', __('Coupon deleted successfully.'));
    }

    public function render()
    {
        $query = PlatformCoupon::with('operator')->latest('created_at');

        if (! empty($this->search)) {
            $s = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', $s)
                    ->orWhere('description', 'like', $s)
                    ->orWhereHas('operator', fn ($oq) => $oq->where('name', 'like', $s));
            });
        }

        if ($this->status_filter === 'active') {
            $query->active();
        } elseif ($this->status_filter === 'expired') {
            $query->where('expires_at', '<', now());
        }

        $coupons = $query->get();
        $operators = Operator::orderBy('name')->get();

        $activeCount = PlatformCoupon::active()->count();
        $totalRedemptions = PlatformCoupon::sum('used_count');

        return view('pages.admin.⚡coupons', [
            'coupons' => $coupons,
            'operators' => $operators,
            'activeCount' => $activeCount,
            'totalRedemptions' => $totalRedemptions,
        ]);
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="p-2.5 rounded-2xl bg-purple-100 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300">
                <i class="fa-solid fa-ticket text-lg"></i>
            </span>
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ __('Platform Coupons & Discount Engine') }}
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Create and manage cross-operator discount campaigns, promo codes, and redemption limits.') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="openCreateModal"
                class="h-9 px-3.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-xs transition flex items-center gap-1.5 cursor-pointer"
            >
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>{{ __('New Promo Code') }}</span>
            </button>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if (session()->has('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-sm text-emerald-600 dark:text-emerald-400"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Active Promo Codes') }}</span>
            <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ $activeCount }}</div>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Available for customer checkout') }}</p>
        </div>

        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <span class="text-xs font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">{{ __('Total Redemptions') }}</span>
            <div class="text-2xl sm:text-3xl font-black text-purple-600 dark:text-purple-400">{{ $totalRedemptions }}</div>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Coupons claimed by guests') }}</p>
        </div>

        <div class="p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-1">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __('Engine Mode') }}</span>
            <div class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ __('Automated') }}</div>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Real-time checkout validation') }}</p>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <!-- Search -->
        <div class="relative w-full sm:w-80">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <x-input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="{{ __('Search code or description...') }}"
                class="pl-9 text-xs"
            />
        </div>

        <!-- Status Filter Pills -->
        <div class="flex items-center gap-1.5 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0">
            @php
                $statusTabs = [
                    'all' => __('All'),
                    'active' => __('Active (:count)', ['count' => $activeCount]),
                    'expired' => __('Expired / Ended'),
                ];
            @endphp

            @foreach ($statusTabs as $val => $label)
                <button
                    type="button"
                    wire:click="$set('status_filter', '{{ $val }}')"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer {{ $status_filter === $val ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-zinc-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Coupons Table -->
    <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-zinc-800/40 border-b border-slate-200/80 dark:border-zinc-800 text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <th class="py-3.5 px-4 sm:px-6">{{ __('Promo Code') }}</th>
                        <th class="py-3.5 px-4">{{ __('Discount') }}</th>
                        <th class="py-3.5 px-4">{{ __('Scope & Operator') }}</th>
                        <th class="py-3.5 px-4">{{ __('Redemptions / Limits') }}</th>
                        <th class="py-3.5 px-4">{{ __('Validity Window') }}</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                    @forelse ($coupons as $coupon)
                        @php
                            $isExpired = $coupon->expires_at && $coupon->expires_at->isPast();
                            $isLimitReached = $coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses;
                        @endphp
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-800/30 transition">
                            <!-- Code -->
                            <td class="py-3.5 px-4 sm:px-6">
                                <div class="space-y-0.5">
                                    <span class="inline-flex items-center gap-1.5 font-mono font-black text-sm text-purple-700 dark:text-purple-300 px-2.5 py-1 rounded-xl bg-purple-50 dark:bg-purple-950/60 border border-purple-200 dark:border-purple-800">
                                        <i class="fa-solid fa-tag text-xs"></i>
                                        {{ $coupon->code }}
                                    </span>
                                    @if ($coupon->description)
                                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-xs">{{ $coupon->description }}</p>
                                    @endif
                                </div>
                            </td>

                            <!-- Discount Value -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-0.5">
                                    <div class="font-extrabold text-slate-900 dark:text-white text-sm">
                                        @if ($coupon->discount_type === 'percentage')
                                            {{ (float) $coupon->discount_value }}% {{ __('OFF') }}
                                        @else
                                            Rp {{ number_format((float) $coupon->discount_value, 0, ',', '.') }} {{ __('OFF') }}
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
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

                            <!-- Scope -->
                            <td class="py-3.5 px-4">
                                @if ($coupon->operator)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                        <i class="fa-solid fa-building text-[10px]"></i>
                                        {{ $coupon->operator->name }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                                        <i class="fa-solid fa-earth-asia text-[10px]"></i>
                                        {{ __('Platform-Wide (All)') }}
                                    </span>
                                @endif
                            </td>

                            <!-- Redemptions -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-0.5">
                                    <span class="font-bold text-xs sm:text-sm text-slate-800 dark:text-slate-200">
                                        {{ $coupon->used_count }}
                                        <span class="text-slate-400 font-normal">/ {{ $coupon->max_uses ? $coupon->max_uses . ' max' : '∞' }}</span>
                                    </span>
                                    @if ($isLimitReached)
                                        <span class="block text-xs font-bold text-rose-500 uppercase">{{ __('Limit Reached') }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Validity Window -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-0.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-extrabold uppercase {{ ! $coupon->is_active ? 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-slate-400' : ($isExpired ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300') }}">
                                        {{ ! $coupon->is_active ? __('Inactive') : ($isExpired ? __('Expired') : __('Active')) }}
                                    </span>
                                    @if ($coupon->expires_at)
                                        <p class="font-mono text-xs text-slate-500 dark:text-slate-400">
                                            {{ __('Expires:') }} {{ $coupon->expires_at->format('d M Y') }}
                                        </p>
                                    @endif
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        wire:click="toggleActive('{{ $coupon->id }}')"
                                        class="h-8 px-2 rounded-lg text-xs font-bold transition {{ $coupon->is_active ? 'bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}"
                                        title="{{ __('Toggle active status') }}"
                                    >
                                        {{ $coupon->is_active ? __('Pause') : __('Activate') }}
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="editCoupon('{{ $coupon->id }}')"
                                        class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 text-xs transition"
                                        title="{{ __('Edit Coupon') }}"
                                    >
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="deleteCoupon('{{ $coupon->id }}')"
                                        wire:confirm="{{ __('Are you sure you want to delete promo code :code?', ['code' => $coupon->code]) }}"
                                        class="h-8 w-8 inline-flex items-center justify-center rounded-lg bg-rose-50 dark:bg-rose-950/70 text-rose-600 hover:bg-rose-100 text-xs transition"
                                        title="{{ __('Delete Coupon') }}"
                                    >
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                {{ __('No coupon codes match your search or filter.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create / Edit Modal -->
    @if ($show_modal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-lg rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xl flex flex-col my-8">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40 rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-purple-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-ticket"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    {{ $editing_id ? __('Edit Promo Code') : __('Create New Promo Code') }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    {{ __('Configure discount value, storefront scope, and usage conditions.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeModal" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form wire:submit="saveCoupon" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto pb-36">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="code" :value="__('Coupon Code (e.g. SUMMER26)')" required />
                                <x-input id="code" type="text" wire:model="code" placeholder="{{ __('SUMMER26') }}" class="font-mono uppercase font-black" :error="$errors->has('code')" />
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
                            <x-label for="description" :value="__('Campaign Description')" />
                            <x-input id="description" type="text" wire:model="description" placeholder="{{ __('e.g. Early bird season launch promo') }}" :error="$errors->has('description')" />
                            <x-input-error :messages="$errors->get('description')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100 dark:border-zinc-800">
                            <div>
                                <x-label for="discount_value" :value="$discount_type === 'percentage' ? __('Discount (%)') : __('Discount (Rp)')" required />
                                <x-input id="discount_value" type="number" step="0.1" wire:model="discount_value" class="font-bold font-mono" :error="$errors->has('discount_value')" />
                                <x-input-error :messages="$errors->get('discount_value')" />
                            </div>

                            <div>
                                <x-label for="min_spend" :value="__('Min Spend (Rp)')" required />
                                <x-input id="min_spend" type="number" step="1000" wire:model="min_spend" class="font-bold" :error="$errors->has('min_spend')" />
                                <x-input-error :messages="$errors->get('min_spend')" />
                            </div>

                            <div>
                                <x-label for="max_discount_amount" :value="__('Max Cap (Rp)')" />
                                <x-input id="max_discount_amount" type="number" step="1000" wire:model="max_discount_amount" placeholder="{{ __('No Cap') }}" :error="$errors->has('max_discount_amount')" />
                                <x-input-error :messages="$errors->get('max_discount_amount')" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="operator_id" :value="__('Storefront Scope')" />
                                @php
                                    $operatorOptions = collect([['value' => '', 'label' => __('Platform-Wide (All Storefronts)')]])
                                        ->concat($operators->map(fn($op) => ['value' => (string) $op->id, 'label' => $op->name . ' ' . __('only')]))
                                        ->toArray();
                                @endphp
                                <x-select
                                    id="operator_id"
                                    wire:model="operator_id"
                                    :options="$operatorOptions"
                                    :error="$errors->has('operator_id')"
                                />
                                <x-input-error :messages="$errors->get('operator_id')" />
                            </div>

                            <div>
                                <x-label for="max_uses" :value="__('Total Max Uses (Blank = ∞)')" />
                                <x-input id="max_uses" type="number" wire:model="max_uses" placeholder="{{ __('Unlimited') }}" :error="$errors->has('max_uses')" />
                                <x-input-error :messages="$errors->get('max_uses')" />
                            </div>
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

                        <div class="pt-3 border-t border-slate-100 dark:border-zinc-800">
                            <div class="p-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 hover:bg-slate-100 dark:hover:bg-zinc-800 transition">
                                <x-checkbox
                                    id="coupon_is_active"
                                    wire:model="is_active"
                                    :label="__('Enable coupon immediately (Active)')"
                                    :description="__('Guests will be able to apply this promo code right away')"
                                />
                            </div>
                        </div>

                        <!-- Modal Actions Footer -->
                        <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-end gap-3">
                            <x-button type="button" variant="secondary" wire:click="closeModal" class="text-xs font-bold">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit" variant="primary" class="text-xs font-bold bg-purple-600 hover:bg-purple-700">
                                <i class="fa-solid fa-floppy-disk mr-1.5 text-xs"></i>
                                <span>{{ __('Save Promo Code') }}</span>
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif
</div>
