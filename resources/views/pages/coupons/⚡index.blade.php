<?php

use App\Models\Operator;
use App\Models\PlatformCoupon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app.sidebar')] #[Title('Coupons & Discounts - Operator Portal')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all'; // all, active, expired

    public bool $show_modal = false;
    public ?string $editing_id = null;

    // Confirmation modal states
    public ?string $confirming_delete_id = null;
    public ?string $confirming_delete_code = null;
    public ?string $confirming_toggle_id = null;
    public ?string $confirming_toggle_code = null;
    public bool $confirming_toggle_current_state = false;

    // Form fields
    public string $code = '';
    public string $description = '';
    public string $discount_type = 'percentage';
    public float $discount_value = 10.0;
    public float $min_spend = 0.0;
    public ?float $max_discount_amount = null;
    public ?int $max_uses = null;
    public bool $is_active = true;
    public ?string $starts_at = null;
    public ?string $expires_at = null;

    /**
     * Get current operator for authenticated user.
     */
    public function getOperatorProperty(): ?Operator
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user->currentOperator();
    }

    /**
     * Rules for validating a coupon.
     */
    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'alpha_num:ascii'],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'string', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'min_spend' => ['required', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    /**
     * Open create modal.
     */
    public function createCoupon(): void
    {
        $this->resetErrorBag();
        $this->editing_id = null;
        $this->code = '';
        $this->description = '';
        $this->discount_type = 'percentage';
        $this->discount_value = 10.0;
        $this->min_spend = 0.0;
        $this->max_discount_amount = null;
        $this->max_uses = null;
        $this->is_active = true;
        $this->starts_at = now()->format('Y-m-d H:i');
        $this->expires_at = now()->addMonths(1)->format('Y-m-d H:i');
        $this->show_modal = true;
    }

    /**
     * Open modal for create or edit.
     */
    public function openModal(?string $id = null): void
    {
        if ($id) {
            $this->editCoupon($id);
        } else {
            $this->createCoupon();
        }
    }

    /**
     * Open edit modal for an existing operator coupon.
     */
    public function editCoupon(string $id): void
    {
        $this->resetErrorBag();
        $operator = $this->operator;

        if (! $operator) {
            return;
        }

        $coupon = PlatformCoupon::where('operator_id', $operator->id)->find($id);

        if (! $coupon) {
            session()->flash('error', __('Coupon not found.'));

            return;
        }

        $this->editing_id = $coupon->id;
        $this->code = $coupon->code;
        $this->description = $coupon->description ?? '';
        $this->discount_type = $coupon->discount_type;
        $this->discount_value = (float) $coupon->discount_value;
        $this->min_spend = (float) $coupon->min_spend;
        $this->max_discount_amount = $coupon->max_discount_amount ? (float) $coupon->max_discount_amount : null;
        $this->max_uses = $coupon->max_uses;
        $this->is_active = (bool) $coupon->is_active;
        $this->starts_at = $coupon->starts_at?->format('Y-m-d H:i');
        $this->expires_at = $coupon->expires_at?->format('Y-m-d H:i');
        $this->show_modal = true;
    }

    /**
     * Save newly created or edited coupon.
     */
    public function saveCoupon(): void
    {
        $operator = $this->operator;

        if (! $operator) {
            session()->flash('error', __('Operator account required.'));

            return;
        }

        $this->code = strtoupper(trim($this->code));
        $validated = $this->validate();

        // Check unique code per operator
        $duplicate = PlatformCoupon::where('operator_id', $operator->id)
            ->where('code', $this->code)
            ->when($this->editing_id, fn ($q) => $q->where('id', '!=', $this->editing_id))
            ->exists();

        if ($duplicate) {
            $this->addError('code', __('A promo code with this name already exists for your storefront.'));

            return;
        }

        $payload = [
            'code' => $this->code,
            'description' => $this->description ?: null,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'min_spend' => $this->min_spend,
            'max_discount_amount' => $this->discount_type === 'percentage' ? $this->max_discount_amount : null,
            'operator_id' => $operator->id,
            'max_uses' => $this->max_uses,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at ?: null,
            'expires_at' => $this->expires_at ?: null,
        ];

        if ($this->editing_id) {
            $coupon = PlatformCoupon::where('operator_id', $operator->id)->find($this->editing_id);
            if ($coupon) {
                $coupon->update($payload);
                session()->flash('success', __('Promo code :code updated successfully.', ['code' => $this->code]));
            }
        } else {
            PlatformCoupon::create($payload);
            session()->flash('success', __('Promo code :code created successfully.', ['code' => $this->code]));
        }

        $this->show_modal = false;
        $this->editing_id = null;
    }

    /**
     * Prompt toggle active confirmation modal.
     */
    public function promptToggleActive(string $id, string $code, bool $currentActive): void
    {
        $this->confirming_toggle_id = $id;
        $this->confirming_toggle_code = $code;
        $this->confirming_toggle_current_state = $currentActive;
    }

    /**
     * Cancel toggle active modal.
     */
    public function cancelToggleActive(): void
    {
        $this->confirming_toggle_id = null;
        $this->confirming_toggle_code = null;
    }

    /**
     * Confirm and execute toggle active.
     */
    public function confirmToggleActive(): void
    {
        if ($this->confirming_toggle_id) {
            $this->toggleActive($this->confirming_toggle_id);
            $this->cancelToggleActive();
        }
    }

    /**
     * Toggle active state of an operator coupon.
     */
    public function toggleActive(string $id): void
    {
        $operator = $this->operator;
        if (! $operator) {
            return;
        }

        $coupon = PlatformCoupon::where('operator_id', $operator->id)->find($id);

        if ($coupon) {
            $coupon->update(['is_active' => ! $coupon->is_active]);
            session()->flash('success', __('Promo code status updated for :code.', ['code' => $coupon->code]));
        }
    }

    /**
     * Prompt delete confirmation modal.
     */
    public function promptDelete(string $id, string $code): void
    {
        $this->confirming_delete_id = $id;
        $this->confirming_delete_code = $code;
    }

    /**
     * Cancel delete modal.
     */
    public function cancelDelete(): void
    {
        $this->confirming_delete_id = null;
        $this->confirming_delete_code = null;
    }

    /**
     * Confirm and execute delete.
     */
    public function confirmDelete(): void
    {
        if ($this->confirming_delete_id) {
            $this->deleteCoupon($this->confirming_delete_id);
            $this->cancelDelete();
        }
    }

    /**
     * Delete an operator coupon.
     */
    public function deleteCoupon(string $id): void
    {
        $operator = $this->operator;
        if (! $operator) {
            return;
        }

        $coupon = PlatformCoupon::where('operator_id', $operator->id)->find($id);

        if ($coupon) {
            $code = $coupon->code;
            $coupon->delete();
            session()->flash('success', __('Promo code :code deleted.', ['code' => $code]));
        }
    }

    /**
     * Close modal dialog.
     */
    public function closeModal(): void
    {
        $this->show_modal = false;
        $this->editing_id = null;
    }

    /**
     * Render the Livewire component view.
     */
    public function render()
    {
        $operator = $this->operator;

        $query = PlatformCoupon::where('operator_id', $operator?->id ?? 'none')
            ->latest();

        if (! empty($this->search)) {
            $s = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', $s)
                    ->orWhere('description', 'like', $s);
            });
        }

        if ($this->filter === 'active') {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                });
        } elseif ($this->filter === 'expired') {
            $query->where(function ($q) {
                $q->where('is_active', false)
                    ->orWhere('expires_at', '<', now());
            });
        }

        $coupons = $query->paginate(12);

        // Summary metrics
        $totalCoupons = PlatformCoupon::where('operator_id', $operator?->id)->count();
        $activeCoupons = PlatformCoupon::where('operator_id', $operator?->id)->active()->count();
        $totalRedemptions = PlatformCoupon::where('operator_id', $operator?->id)->sum('used_count');

        return view('pages.coupons.⚡index', [
            'coupons' => $coupons,
            'totalCoupons' => $totalCoupons,
            'activeCoupons' => $activeCoupons,
            'totalRedemptions' => $totalRedemptions,
        ]);
    }
}; ?>

<div class="space-y-6">
    <x-page-header
        :title="__('Coupons & Promo Codes')"
        :subtitle="__('Create discount codes to incentivize guest bookings, reward repeat clients, and run seasonal campaigns.')"
        icon="fa-ticket"
    >
        <x-slot:actions>
            <x-button type="button" wire:click="createCoupon">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>{{ __('New Promo Code') }}</span>
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <!-- Alert Notifications -->
    @if (session()->has('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400 text-sm"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 rounded-2xl bg-rose-50 text-rose-800 dark:bg-rose-950/70 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600 dark:text-rose-400 text-sm"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
        <x-metric-card
            :label="__('Total Codes')"
            :value="$totalCoupons"
            :hint="__('Storefront promotional codes')"
            icon="fa-ticket"
            tone="neutral"
        />
        <x-metric-card
            :label="__('Active On Storefront')"
            :value="$activeCoupons"
            :hint="__('Currently redeemable by guests')"
            icon="fa-circle-check"
            tone="success"
        />
        <x-metric-card
            :label="__('Guest Redemptions')"
            :value="$totalRedemptions"
            :hint="__('Total bookings with discounts')"
            icon="fa-tag"
            tone="brand"
        />
    </div>

    <x-toolbar class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <div class="w-full sm:w-80">
            <x-search-input
                wire:model.live.debounce.300ms="search"
                :placeholder="__('Search by code or description...')"
            />
        </div>
        <x-filter-tabs>
            @foreach (['all' => __('All Codes'), 'active' => __('Active'), 'expired' => __('Inactive / Expired')] as $k => $label)
                <x-filter-tab :active="$filter === $k" wire:click="$set('filter', '{{ $k }}')">
                    {{ $label }}
                </x-filter-tab>
            @endforeach
        </x-filter-tabs>
    </x-toolbar>

    <!-- Coupons Table Card -->
    <div class="rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-[#10141d] border-b border-slate-200/80 dark:border-[#1e2433] text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        <th class="py-3.5 px-4 sm:px-6">{{ __('Promo Code') }}</th>
                        <th class="py-3.5 px-4">{{ __('Discount') }}</th>
                        <th class="py-3.5 px-4">{{ __('Usage & Limits') }}</th>
                        <th class="py-3.5 px-4">{{ __('Validity Window') }}</th>
                        <th class="py-3.5 px-4 text-center">{{ __('Status') }}</th>
                        <th class="py-3.5 px-4 sm:px-6 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                    @forelse ($coupons as $c)
                        @php
                            $isExpired = $c->expires_at && $c->expires_at->isPast();
                            $isFuture = $c->starts_at && $c->starts_at->isFuture();
                            $isLimitReached = $c->max_uses !== null && $c->used_count >= $c->max_uses;
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-[#141824]/80 transition group">
                            <!-- Code & Description -->
                            <td class="py-3.5 px-4 sm:px-6">
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-[#FFEF4D]/10 text-slate-800 dark:text-[#FFEF4D] border border-slate-200 dark:border-[#FFEF4D]/30 font-mono font-bold text-xs sm:text-sm">
                                            {{ $c->code }}
                                        </span>
                                    </div>
                                    @if ($c->description)
                                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-xs">{{ $c->description }}</p>
                                    @endif
                                </div>
                            </td>

                            <!-- Discount Rate -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-0.5">
                                    <span class="font-extrabold text-sm text-slate-900 dark:text-white font-mono">
                                        @if ($c->discount_type === 'percentage')
                                             {{ (float) $c->discount_value }}% OFF
                                        @else
                                            Rp {{ number_format((float) $c->discount_value, 0, ',', '.') }} OFF
                                        @endif
                                    </span>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        @if ($c->min_spend > 0)
                                            <span>Min: Rp {{ number_format((float) $c->min_spend, 0, ',', '.') }}</span>
                                        @else
                                            <span>{{ __('No min spend') }}</span>
                                        @endif
                                        @if ($c->max_discount_amount)
                                            <span>&bull; Cap: Rp {{ number_format((float) $c->max_discount_amount, 0, ',', '.') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Usage & Limits -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-0.5 font-mono text-xs">
                                    <span class="font-bold text-slate-900 dark:text-white">
                                        {{ $c->used_count }} / {{ $c->max_uses ?? '∞' }} {{ __('uses') }}
                                    </span>
                                    @if ($isLimitReached)
                                        <span class="block text-[10px] text-rose-500 font-bold uppercase">{{ __('Limit Reached') }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Validity Window -->
                            <td class="py-3.5 px-4">
                                <div class="space-y-0.5 text-xs">
                                    @if ($c->expires_at)
                                        <div class="font-medium {{ $isExpired ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-700 dark:text-slate-300' }}">
                                            {{ __('Expires:') }} {{ $c->expires_at->format('d M Y, H:i') }}
                                        </div>
                                    @else
                                        <span class="text-slate-400 font-semibold">{{ __('Never Expires') }}</span>
                                    @endif

                                    @if ($isFuture)
                                        <span class="text-[10px] text-amber-500 font-bold uppercase block">{{ __('Starts:') }} {{ $c->starts_at->format('d M Y') }}</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Status Toggle -->
                            <td class="py-3.5 px-4 text-center">
                                <button
                                    type="button"
                                    wire:click="promptToggleActive('{{ $c->id }}', '{{ $c->code }}', {{ $c->is_active && ! $isExpired ? 'true' : 'false' }})"
                                    class="h-8 px-3 rounded-full inline-flex items-center gap-1.5 text-xs font-bold transition cursor-pointer {{ $c->is_active && ! $isExpired ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800/60' : 'bg-slate-100 text-slate-700 dark:bg-[#141821] dark:text-slate-300 border border-slate-200 dark:border-[#1e2433]' }}"
                                    title="{{ __('Click to change active status') }}"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full {{ $c->is_active && ! $isExpired ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-slate-400' }}"></span>
                                    <span>{{ $c->is_active && ! $isExpired ? __('Active') : __('Inactive') }}</span>
                                </button>
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 sm:px-6 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        type="button"
                                        wire:click="editCoupon('{{ $c->id }}')"
                                        class="h-8 px-3 rounded-xl bg-slate-100 dark:bg-[#141721] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-[#262d3d] font-bold text-xs transition inline-flex items-center gap-1 cursor-pointer shadow-2xs"
                                        title="{{ __('Edit Code') }}"
                                    >
                                        <i class="fa-solid fa-pen text-[10px]"></i>
                                        <span>{{ __('Edit') }}</span>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="promptDelete('{{ $c->id }}', '{{ $c->code }}')"
                                        class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-[#141721] hover:bg-rose-50 dark:hover:bg-rose-950/50 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 border border-slate-200 dark:border-[#262d3d] inline-flex items-center justify-center transition cursor-pointer shadow-2xs"
                                        title="{{ __('Delete Code') }}"
                                    >
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-12 text-center text-slate-400 dark:text-slate-500">
                                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-[#141821] text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-[#1e2433] flex items-center justify-center mx-auto text-xl mb-3">
                                    <i class="fa-solid fa-tags"></i>
                                </div>
                                <p class="font-bold text-slate-700 dark:text-slate-300 text-sm">{{ __('No promo codes found') }}</p>
                                <p class="text-xs text-slate-500 mt-1">{{ __('Create marketing discount codes to incentivize direct guest bookings on your storefront.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($coupons->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-[#1e2433] bg-slate-50/50 dark:bg-[#10141d]">
                {{ $coupons->links() }}
            </div>
        @endif
    </div>

    <!-- Delete Confirmation Modal -->
    @if ($confirming_delete_id)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs">
                <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xl p-6 space-y-4 text-center animate-fade-in">
                    <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/80 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto text-lg">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>

                    <div class="space-y-1.5">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ __('Delete Promo Code ":code"?', ['code' => $confirming_delete_code]) }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-xs mx-auto leading-relaxed">
                            {{ __('Are you sure you want to permanently delete this promo code? Guests will no longer be able to use it on your storefront.') }}
                        </p>
                    </div>

                    <div class="flex items-center justify-center gap-3 pt-2">
                        <x-button
                            type="button"
                            variant="secondary"
                            wire:click="cancelDelete"
                            class="font-semibold text-xs"
                        >
                            {{ __('Cancel') }}
                        </x-button>
                        <x-button
                            type="button"
                            variant="danger"
                            wire:click="confirmDelete"
                            class="font-semibold text-xs shadow-xs"
                        >
                            <i class="fa-solid fa-trash-can mr-1.5 text-xs"></i>
                            {{ __('Confirm Delete') }}
                        </x-button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Toggle Active Status Confirmation Modal -->
    @if ($confirming_toggle_id)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs">
                <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xl p-6 space-y-4 text-center animate-fade-in">
                    <div class="w-12 h-12 rounded-2xl {{ $confirming_toggle_current_state ? 'bg-amber-100 dark:bg-amber-950/80 text-amber-600 dark:text-amber-400' : 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-600 dark:text-emerald-400' }} flex items-center justify-center mx-auto text-lg">
                        <i class="fa-solid {{ $confirming_toggle_current_state ? 'fa-pause' : 'fa-play' }}"></i>
                    </div>

                    <div class="space-y-1.5">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ $confirming_toggle_current_state ? __('Deactivate Promo Code ":code"?', ['code' => $confirming_toggle_code]) : __('Activate Promo Code ":code"?', ['code' => $confirming_toggle_code]) }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-xs mx-auto leading-relaxed">
                            @if ($confirming_toggle_current_state)
                                {{ __('Deactivating this code will immediately prevent guests from redeeming it in the storefront booking box until re-enabled.') }}
                            @else
                                {{ __('Activating this code will make it immediately redeemable by guests booking on your storefront.') }}
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center justify-center gap-3 pt-2">
                        <x-button
                            type="button"
                            variant="secondary"
                            wire:click="cancelToggleActive"
                            class="font-semibold text-xs"
                        >
                            {{ __('Cancel') }}
                        </x-button>
                        <x-button
                            type="button"
                            variant="{{ $confirming_toggle_current_state ? 'secondary' : 'primary' }}"
                            wire:click="confirmToggleActive"
                            class="font-semibold text-xs {{ $confirming_toggle_current_state ? 'text-amber-700 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-emerald-600 hover:bg-emerald-700 text-white' }}"
                        >
                            <i class="fa-solid {{ $confirming_toggle_current_state ? 'fa-circle-pause' : 'fa-circle-check' }} mr-1.5 text-xs"></i>
                            {{ $confirming_toggle_current_state ? __('Deactivate Code') : __('Activate Code') }}
                        </x-button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Create / Edit Modal Dialog -->
    @if ($show_modal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-lg rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xl flex flex-col my-8">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-[#1e2433] flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-[#10141d] rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-[#FFEF4D] text-[#090d16] flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-ticket"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    {{ $editing_id ? __('Edit Storefront Promo Code') : __('Create Storefront Promo Code') }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    {{ __('Set discount percentage, minimum spend, and guest validity dates.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeModal" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body Form -->
                    <form wire:submit="saveCoupon" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
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
                            <x-label for="description" :value="__('Campaign Description / Internal Note')" />
                            <x-input id="description" type="text" wire:model="description" placeholder="{{ __('e.g. 10% discount for early bird bookings') }}" :error="$errors->has('description')" />
                            <x-input-error :messages="$errors->get('description')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100 dark:border-[#1e2433]">
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

                        <div>
                            <x-label for="max_uses" :value="__('Total Max Uses (Blank = Unlimited)')" />
                            <x-input id="max_uses" type="number" wire:model="max_uses" placeholder="{{ __('Unlimited') }}" :error="$errors->has('max_uses')" />
                            <x-input-error :messages="$errors->get('max_uses')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100 dark:border-[#1e2433]">
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

                        <div class="pt-3 border-t border-slate-100 dark:border-[#1e2433]">
                            <div class="p-3 rounded-2xl border border-slate-200/80 dark:border-[#1e2433] bg-slate-50/50 dark:bg-[#141821]/50 hover:bg-slate-100 dark:hover:bg-[#141821] transition">
                                <x-checkbox
                                    id="coupon_is_active"
                                    wire:model="is_active"
                                    :label="__('Enable coupon immediately (Active on Storefront)')"
                                    :description="__('Guests will be able to apply this code during checkout right away')"
                                />
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="pt-4 border-t border-slate-100 dark:border-[#1e2433] flex items-center justify-end gap-3">
                            <x-button type="button" variant="secondary" wire:click="closeModal" class="text-xs font-bold">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit" variant="primary" class="text-xs font-bold">
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
