<?php

use App\Contracts\Bookable;
use App\Enums\ReservationStatus;
use App\Exceptions\CapacityUnavailableException;
use App\Models\Operator;
use App\Models\Package;
use App\Models\PlatformCoupon;
use App\Models\Product;
use App\Models\Reservation;
use App\Services\CapacityService;
use App\Services\DokuPaymentService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Bookable $bookable;
    public Operator $operator;
    public ?Operator $agent = null;

    public string $requested_date = '';
    public int $pax_count = 1;
    public string $guest_name = '';
    public string $guest_contact = '';
    public string $guest_email = '';
    public string $notes = '';
    public bool $agreed_terms = false;

    // Coupon Engine State
    public string $couponCode = '';
    public ?string $appliedCouponCode = null;
    public float $discountAmount = 0.0;
    public string $couponMessage = '';
    public bool $couponValid = false;

    public bool $showSuccess = false;
    public ?string $checkoutUrl = null;

    public function mount(Bookable $bookable, Operator|null $operator = null, Operator|null $agent = null): void
    {
        $this->bookable = $bookable;
        $resolved = $operator ?? $agent ?? ($bookable instanceof \App\Contracts\Bookable ? $bookable->getOperator() : null);
        $this->operator = $resolved;
        $this->agent = $resolved;

        // Default requested trip date to 2 days ahead
        $this->requested_date = now()->addDays(2)->format('Y-m-d');
    }

    #[Computed]
    public function unitPrice(): float
    {
        return (float) $this->bookable->price;
    }

    #[Computed]
    public function subtotal(): float
    {
        return $this->unitPrice * $this->pax_count;
    }

    #[Computed]
    public function serviceFeeRate(): float
    {
        return \App\Models\PlatformSetting::current()->getGuestServiceFeeRate();
    }

    #[Computed]
    public function serviceFee(): float
    {
        return \App\Models\PlatformSetting::current()->calculateGuestServiceFee($this->subtotal, $this->operator);
    }

    #[Computed]
    public function totalPrice(): float
    {
        return max(0, $this->subtotal + $this->serviceFee - $this->discountAmount);
    }

    public function incrementPax(): void
    {
        $remaining = $this->remainingCapacity;
        $maxPax = $remaining === null ? 50 : min(50, max(1, $remaining));

        if ($this->pax_count < $maxPax) {
            $this->pax_count++;
            $this->revalidateAppliedCoupon();
        }
    }

    public function decrementPax(): void
    {
        if ($this->pax_count > 1) {
            $this->pax_count--;
            $this->revalidateAppliedCoupon();
        }
    }

    /**
     * Check if this operator has any currently active promotional coupons.
     */
    public function getHasActiveCouponsProperty(): bool
    {
        return PlatformCoupon::where('operator_id', $this->operator->id)->active()->exists();
    }

    /**
     * Validate and apply entered promotional coupon code.
     */
    public function applyCoupon(): void
    {
        $cleanCode = strtoupper(trim($this->couponCode));

        if (empty($cleanCode)) {
            $this->couponMessage = __('Please enter a promo code.');
            $this->couponValid = false;

            return;
        }

        $coupon = PlatformCoupon::where('operator_id', $this->operator->id)
            ->where('code', $cleanCode)
            ->first();

        if (! $coupon) {
            $this->couponMessage = __('Invalid promo code.');
            $this->couponValid = false;
            $this->removeCoupon();

            return;
        }

        $result = $coupon->validateFor($this->subtotal, $this->operator->id);

        if (! $result['valid'] || ($result['discount'] ?? 0) <= 0) {
            $this->couponMessage = $result['reason'] ?? __('Promo code cannot be applied.');
            $this->couponValid = false;
            $this->removeCoupon();

            return;
        }

        $this->appliedCouponCode = $coupon->code;
        $this->discountAmount = (float) $result['discount'];
        $this->couponValid = true;
        $this->couponMessage = __('Code :code applied! Saved Rp :amount', [
            'code' => $coupon->code,
            'amount' => number_format($this->discountAmount, 0, ',', '.'),
        ]);
    }

    /**
     * Remove applied coupon.
     */
    public function removeCoupon(): void
    {
        $this->appliedCouponCode = null;
        $this->discountAmount = 0.0;
        $this->couponCode = '';
        $this->couponValid = false;
    }

    protected function revalidateAppliedCoupon(): void
    {
        if ($this->appliedCouponCode) {
            $coupon = PlatformCoupon::where('operator_id', $this->operator->id)
                ->where('code', $this->appliedCouponCode)
                ->first();
            if ($coupon) {
                $result = $coupon->validateFor($this->subtotal, $this->operator->id);
                if ($result['valid'] && ($result['discount'] ?? 0) > 0) {
                    $this->discountAmount = (float) $result['discount'];
                } else {
                    $reason = $result['reason'] ?? __('Promo code is no longer applicable.');
                    $this->removeCoupon();
                    $this->couponMessage = $reason;
                    $this->couponValid = false;
                }
            } else {
                $this->removeCoupon();
            }
        }
    }

    /**
     * Seats still available on the selected date, or null when uncapped.
     */
    #[Computed]
    public function remainingCapacity(): ?int
    {
        if ($this->requested_date === '') {
            return null;
        }

        return app(CapacityService::class)->remainingCapacity($this->bookable, $this->requested_date);
    }

    #[Computed]
    public function isSoldOut(): bool
    {
        return $this->remainingCapacity !== null && $this->remainingCapacity <= 0;
    }

    /**
     * Refresh availability messaging whenever the guest picks a different date.
     */
    public function updatedRequestedDate(): void
    {
        unset($this->remainingCapacity, $this->isSoldOut);

        $remaining = $this->remainingCapacity;

        if ($remaining !== null && $remaining > 0 && $this->pax_count > $remaining) {
            $this->pax_count = $remaining;
            $this->revalidateAppliedCoupon();
        }
    }

    #[Computed]
    public function blackoutDates(): array
    {
        if (method_exists($this->bookable, 'getBlackoutDates')) {
            return $this->bookable->getBlackoutDates();
        }

        return [];
    }

    /**
     * Submit reservation and initiate DOKU checkout.
     */
    public function submitBooking(DokuPaymentService $paymentService): void
    {
        $minDate = now()->startOfDay()->addHours($this->bookable->advance_booking_hours ?? 0);

        $this->validate([
            'requested_date' => ['required', 'date', 'after_or_equal:' . $minDate->toDateString()],
            'pax_count' => ['required', 'integer', 'min:1', 'max:50'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_contact' => ['required', 'string', 'min:8', 'max:30'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'agreed_terms' => ['accepted'],
        ], [
            'agreed_terms.accepted' => __('You must agree to the booking and cancellation policy to proceed.'),
            'requested_date.after_or_equal' => __('Please choose a date at least :hours hours in advance.', ['hours' => $this->bookable->advance_booking_hours ?? 0]),
        ]);

        if (method_exists($this->bookable, 'isBlackedOutOn') && $this->bookable->isBlackedOutOn($this->requested_date)) {
            $this->addError('requested_date', __('The selected date (:date) is unavailable for booking due to scheduled maintenance or operator blackout.', ['date' => $this->requested_date]));

            return;
        }

        $termsSnapshot = $this->bookable->generateTermsSnapshot();
        $termsSnapshot['unit_price'] = $this->unitPrice;
        $termsSnapshot['pax_count'] = $this->pax_count;
        $termsSnapshot['subtotal'] = $this->subtotal;
        $termsSnapshot['service_fee'] = $this->serviceFee;
        $termsSnapshot['service_fee_rate'] = $this->serviceFeeRate;
        $termsSnapshot['coupon_code'] = $this->appliedCouponCode;
        $termsSnapshot['discount_amount'] = $this->discountAmount;
        $termsSnapshot['total_price'] = $this->totalPrice;

        try {
            /** @var Reservation $reservation */
            $reservation = app(CapacityService::class)->reserve(
                $this->bookable,
                $this->requested_date,
                $this->pax_count,
                fn (): Reservation => Reservation::query()->create([
                    'bookable_type' => $this->bookable instanceof Package ? 'package' : 'product',
                    'bookable_id' => $this->bookable->id,
                    'operator_id' => $this->operator->id,
                    'guest_name' => $this->guest_name,
                    'guest_contact' => $this->guest_contact,
                    'guest_email' => $this->guest_email ?: null,
                    'requested_date' => $this->requested_date,
                    'pax_count' => $this->pax_count,
                    'notes' => $this->notes ?: null,
                    'terms_snapshot' => $termsSnapshot,
                    'status' => ReservationStatus::PaymentPending,
                    'hold_expires_at' => now()->addMinutes(30),
                ]),
            );
        } catch (CapacityUnavailableException $e) {
            $this->addError('requested_date', $e->getMessage());
            unset($this->remainingCapacity);

            return;
        }

        // Increment coupon usage count if applied
        if ($this->appliedCouponCode) {
            PlatformCoupon::where('operator_id', $this->operator->id)
                ->where('code', $this->appliedCouponCode)
                ->first()
                ?->incrementUsage();
        }

        $session = $paymentService->createPaymentSession($reservation, $this->totalPrice);

        // Send initial booking hold and pay link email if email provided
        if (! empty($reservation->guest_email)) {
            try {
                \Illuminate\Support\Facades\Mail::to($reservation->guest_email)
                    ->send(new \App\Mail\GuestBookingCreatedMail($reservation));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->checkoutUrl = $session['checkout_url'];
        $this->showSuccess = true;

        $this->redirect($this->checkoutUrl);
    }
}; ?>

<div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xl p-5 sm:p-6 space-y-5">
    <div class="space-y-1 border-b border-slate-100 dark:border-zinc-800 pb-4">
        <span class="text-[10px] sm:text-xs text-slate-400 font-black uppercase tracking-wider">{{ __('Price per person') }}</span>
        <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
            Rp {{ number_format($this->unitPrice, 0, ',', '.') }}
        </div>
    </div>

    <!-- Booking Form -->
    <form wire:submit="submitBooking" class="space-y-4">
        <!-- Date Selection -->
        <div>
            <x-label for="requested_date" :value="__('Select Trip Date')" required />
            <x-date-picker
                id="requested_date"
                wire:model.live="requested_date"
                min="{{ now()->addHours($bookable->advance_booking_hours ?? 0)->format('Y-m-d') }}"
                :blackout-dates="$this->blackoutDates"
                :placeholder="__('Choose trip departure date...')"
                :error="$errors->has('requested_date')"
            />
            <x-input-error :messages="$errors->get('requested_date')" />

            <!-- Live Daily Availability -->
            @if ($this->remainingCapacity !== null)
                <div wire:key="availability-{{ $requested_date }}" class="mt-2">
                    @if ($this->isSoldOut)
                        <p class="flex items-center gap-1.5 text-[11px] font-bold text-rose-600 dark:text-rose-400" role="status">
                            <i class="fa-solid fa-circle-xmark text-[10px]" aria-hidden="true"></i>
                            {{ __('Fully booked on this date — please choose another day.') }}
                        </p>
                    @elseif ($this->remainingCapacity <= 5)
                        <p class="flex items-center gap-1.5 text-[11px] font-bold text-amber-600 dark:text-amber-400" role="status">
                            <i class="fa-solid fa-fire text-[10px]" aria-hidden="true"></i>
                            {{ trans_choice('Only :count spot left for this date|Only :count spots left for this date', $this->remainingCapacity, ['count' => $this->remainingCapacity]) }}
                        </p>
                    @else
                        <p class="flex items-center gap-1.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400" role="status">
                            <i class="fa-solid fa-circle-check text-[10px]" aria-hidden="true"></i>
                            {{ trans_choice(':count spot available|:count spots available', $this->remainingCapacity, ['count' => $this->remainingCapacity]) }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <!-- Guests / Pax Counter (Mobile-First Touch Target) -->
        <div>
            <x-label :value="__('Number of guests')" required />
            <div class="flex items-center justify-between p-2 rounded-2xl border border-slate-200 dark:border-zinc-700 bg-slate-50/50 dark:bg-zinc-800/80">
                <span class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200 pl-2">
                    {{ __(':count guests', ['count' => $pax_count]) }}
                </span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="decrementPax"
                        class="h-10 w-10 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 transition cursor-pointer font-black text-base shadow-xs"
                    >
                        -
                    </button>
                    <span class="font-black text-sm w-7 text-center text-slate-900 dark:text-white">{{ $pax_count }}</span>
                    <button
                        type="button"
                        wire:click="incrementPax"
                        @if ($this->remainingCapacity !== null && $this->pax_count >= $this->remainingCapacity) disabled @endif
                        class="h-10 w-10 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-zinc-800 transition cursor-pointer font-black text-base shadow-xs disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        +
                    </button>
                </div>
            </div>
            <x-input-error :messages="$errors->get('pax_count')" />
        </div>

        <!-- Guest Name & WhatsApp -->
        <div class="space-y-3 pt-2 border-t border-slate-100 dark:border-zinc-800">
            <div>
                <x-label for="guest_name" :value="__('Lead Guest Name')" required />
                <x-input id="guest_name" wire:model="guest_name" type="text" placeholder="{{ __('Full Name') }}" class="h-11 text-xs sm:text-sm font-medium" :error="$errors->has('guest_name')" />
                <x-input-error :messages="$errors->get('guest_name')" />
            </div>

            <div>
                <x-label for="guest_contact" :value="__('WhatsApp Contact Phone')" required />
                <x-input id="guest_contact" wire:model="guest_contact" type="tel" placeholder="{{ __('e.g. 081234567890') }}" class="h-11 text-xs sm:text-sm font-medium" :error="$errors->has('guest_contact')" />
                <x-input-error :messages="$errors->get('guest_contact')" />
            </div>

            <div>
                <x-label for="guest_email" :value="__('Email Address (For receipt)')" />
                <x-input id="guest_email" wire:model="guest_email" type="email" placeholder="{{ __('guest@example.com') }}" class="h-11 text-xs sm:text-sm font-medium" :error="$errors->has('guest_email')" />
                <x-input-error :messages="$errors->get('guest_email')" />
            </div>

            <div>
                <x-label for="notes" :value="__('Special Requests / Dietary / Pickup Location')" />
                <textarea
                    id="notes"
                    wire:model="notes"
                    rows="2"
                    placeholder="{{ __('Any notes for the captain or guide...') }}"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs sm:text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition"
                ></textarea>
                <x-input-error :messages="$errors->get('notes')" />
            </div>
        </div>

        <!-- Promo Code Input -->
        <div class="pt-2 border-t border-slate-100 dark:border-zinc-800 space-y-1.5" x-data="{ open: @json($appliedCouponCode || $couponMessage ? true : false) }">
            <div class="flex items-center justify-between">
                <button
                    type="button"
                    @click="open = !open"
                    class="text-xs font-bold text-brand-600 dark:text-brand-400 hover:underline flex items-center gap-1.5 cursor-pointer"
                >
                    <i class="fa-solid fa-ticket text-[11px]"></i>
                    <span>{{ __('Have a promo code?') }}</span>
                    <i class="fa-solid fa-chevron-down text-[9px] transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                </button>
                @if ($appliedCouponCode)
                    <span class="text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/70 border border-emerald-200/60 dark:border-emerald-800/60 px-2 py-0.5 rounded-full">
                        {{ $appliedCouponCode }}
                    </span>
                @endif
            </div>

            <div x-show="open" x-cloak class="space-y-1.5 pt-1">
                @if (! $appliedCouponCode)
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            wire:model="couponCode"
                            wire:keydown.enter.prevent="applyCoupon"
                            placeholder="{{ __('ENTER CODE') }}"
                            class="flex-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs font-mono uppercase font-black text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                        />
                        <button
                            type="button"
                            wire:click="applyCoupon"
                            wire:loading.attr="disabled"
                            wire:target="applyCoupon"
                            class="h-9 px-3.5 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-brand-foreground text-xs font-bold transition shadow-xs cursor-pointer shrink-0 disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <span wire:loading.remove wire:target="applyCoupon">{{ __('Apply') }}</span>
                            <span wire:loading wire:target="applyCoupon"><i class="fa-solid fa-spinner fa-spin text-xs"></i></span>
                        </button>
                    </div>
                @else
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-xs">
                        <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-bold">
                            <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400"></i>
                            <span>{{ $appliedCouponCode }} (-Rp {{ number_format($discountAmount, 0, ',', '.') }})</span>
                        </div>
                        <button
                            type="button"
                            wire:click="removeCoupon"
                            class="text-xs text-rose-600 dark:text-rose-400 hover:underline font-bold cursor-pointer"
                        >
                            {{ __('Remove') }}
                        </button>
                    </div>
                @endif

                @if ($couponMessage && ! $appliedCouponCode)
                    <p class="text-[11px] font-semibold flex items-center gap-1 {{ $couponValid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        <i class="fa-solid {{ $couponValid ? 'fa-circle-check' : 'fa-circle-exclamation' }} text-[10px]"></i>
                        <span>{{ $couponMessage }}</span>
                    </p>
                @endif
            </div>
        </div>

        <!-- Price Summary Breakdown -->
        <div class="p-4 rounded-2xl bg-brand-50/70 dark:bg-brand-950/40 border border-brand-100 dark:border-brand-900/60 space-y-2 text-xs">
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                <span>{{ __(':count x Rp :price', ['count' => $pax_count, 'price' => number_format($this->unitPrice, 0, ',', '.')]) }}</span>
                <span class="font-semibold">Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
            </div>

            @if ($this->serviceFee > 0)
                <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1">
                        <span>{{ __('Service & Payment Fee') }}</span>
                        <span class="text-[10px] text-slate-400">({{ $this->serviceFeeRate * 100 }}%)</span>
                    </span>
                    <span class="font-semibold">Rp {{ number_format($this->serviceFee, 0, ',', '.') }}</span>
                </div>
            @endif

            @if ($this->discountAmount > 0)
                <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400 font-bold">
                    <span class="flex items-center gap-1">
                        <i class="fa-solid fa-tag text-[10px]"></i>
                        <span>{{ __('Promo Discount (:code)', ['code' => $this->appliedCouponCode]) }}</span>
                    </span>
                    <span>- Rp {{ number_format($this->discountAmount, 0, ',', '.') }}</span>
                </div>
            @endif

            <div class="flex items-center justify-between font-black text-sm text-slate-900 dark:text-white pt-2 border-t border-brand-200/60 dark:border-brand-900/60">
                <span>{{ __('Total Amount') }}</span>
                <span class="text-brand-600 dark:text-brand-400 text-base font-black">Rp {{ number_format($this->totalPrice, 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- Mandatory Terms Checkbox -->
        <div class="p-3.5 rounded-2xl bg-slate-50/60 dark:bg-zinc-800/40 border border-slate-200/80 dark:border-zinc-800 transition hover:border-slate-300 dark:hover:border-zinc-700">
            <x-checkbox id="agreed_terms" wire:model.live="agreed_terms" required :error="$errors->has('agreed_terms')">
                <span class="text-[11px] sm:text-xs text-slate-700 dark:text-slate-300 select-none leading-relaxed">
                    {{ __('I agree to the booking terms, free cancellation up to :hours hours before departure, and payment policy.', ['hours' => $bookable->free_cancellation_hours ?? 24]) }}
                </span>
            </x-checkbox>
            <x-input-error :messages="$errors->get('agreed_terms')" />
        </div>

        <!-- Submit Button with Loading State -->
        <button
            type="submit"
            @if (! $agreed_terms || $this->isSoldOut) disabled @endif
            wire:loading.attr="disabled"
            class="w-full h-12 inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-brand-foreground font-black text-sm shadow-md shadow-brand-500/20 transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-brand-600 disabled:shadow-none"
        >
            @if ($this->isSoldOut)
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-circle-xmark text-xs" aria-hidden="true"></i>
                    <span>{{ __('Sold Out on This Date') }}</span>
                </span>
            @else
                <span wire:loading.remove wire:target="submitBooking" class="flex items-center gap-2">
                    <i class="fa-solid fa-lock text-xs" aria-hidden="true"></i>
                    <span>{{ __('Proceed to Secure Payment') }}</span>
                </span>
                <span wire:loading wire:target="submitBooking" class="flex items-center gap-2">
                    <i class="fa-solid fa-circle-notch fa-spin text-xs" aria-hidden="true"></i>
                    <span>{{ __('Creating 30-Min Hold...') }}</span>
                </span>
            @endif
        </button>

        <p class="text-[10px] text-center text-slate-400">
            <i class="fa-solid fa-shield-check mr-1 text-emerald-500"></i>
            {{ __('Instant Payment via QRIS, Virtual Account & Cards') }}
        </p>
    </form>
</div>
