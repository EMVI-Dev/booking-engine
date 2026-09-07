<?php

use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionProrationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Subscription Checkout')] #[Layout('layouts.app')] class extends Component {
    public SubscriptionPayment $payment;

    public string $payment_method = 'cc'; // 'cc' | 'qris' | 'va' | 'direct'

    // Credit Card Form Fields
    public string $card_number = '';
    public string $card_holder = '';
    public string $card_expiry = '';
    public string $card_cvv = '';

    // VA Field
    public string $va_bank = 'BCA';

    public bool $is_processing = false;
    public bool $auto_renew_consent = false;
    public ?string $error_message = null;

    // Platform Subscription Promo Code
    public string $couponCode = '';
    public ?string $appliedCouponCode = null;
    public float $discountAmount = 0.0;
    public string $couponMessage = '';
    public bool $couponValid = false;

    /**
     * Mount checkout component and verify ownership.
     */
    public function mount(SubscriptionPayment $payment): void
    {
        $operator = auth()->user()?->currentOperator();

        if (! $operator || $payment->operator_id !== $operator->id) {
            abort(403, __('Unauthorized subscription payment.'));
        }

        if ($payment->status === 'completed') {
            session()->flash('success', __('This subscription invoice has already been paid and activated.'));
            $this->redirectRoute('settings.plan', navigate: true);
            return;
        }

        $this->payment = $payment;
        $this->payment_method = in_array($payment->gateway, ['cc', 'qris', 'va', 'direct', 'credit_card'], true)
            ? ($payment->gateway === 'credit_card' ? 'cc' : $payment->gateway)
            : 'cc';

        $this->card_holder = auth()->user()->name;

        if (! empty($payment->breakdown['coupon_code'])) {
            $this->appliedCouponCode = (string) $payment->breakdown['coupon_code'];
            $this->discountAmount = (float) ($payment->breakdown['discount_amount'] ?? 0);
            $this->couponValid = true;
        }
    }

    /**
     * Apply platform subscription promo code.
     */
    public function applyCoupon(): void
    {
        $cleanCode = strtoupper(trim($this->couponCode));

        if (empty($cleanCode)) {
            $this->couponMessage = __('Please enter a promo code.');
            $this->couponValid = false;
            return;
        }

        // Platform subscription coupons have operator_id = null
        $coupon = \App\Models\PlatformCoupon::whereNull('operator_id')
            ->where('code', $cleanCode)
            ->first();

        if (! $coupon) {
            $this->couponMessage = __('Invalid subscription promo code.');
            $this->couponValid = false;
            $this->removeCoupon();
            return;
        }

        $gross = (float) $this->payment->gross_amount;
        $result = $coupon->validateFor($gross);

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

        $unusedCredit = (float) ($this->payment->breakdown['unused_credit'] ?? 0);
        $newNet = max(0, $gross - $unusedCredit - $this->discountAmount);
        $breakdown = $this->payment->breakdown ?? [];
        $breakdown['discount_amount'] = $this->discountAmount;
        $breakdown['coupon_code'] = $this->appliedCouponCode;

        $this->payment->update([
            'net_amount_paid' => $newNet,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Remove applied subscription promo code.
     */
    public function removeCoupon(): void
    {
        $this->appliedCouponCode = null;
        $this->discountAmount = 0.0;
        $this->couponCode = '';
        $this->couponValid = false;

        $gross = (float) $this->payment->gross_amount;
        $unusedCredit = (float) ($this->payment->breakdown['unused_credit'] ?? 0);
        $newNet = max(0, $gross - $unusedCredit);
        $breakdown = $this->payment->breakdown ?? [];
        unset($breakdown['discount_amount'], $breakdown['coupon_code']);

        $this->payment->update([
            'net_amount_paid' => $newNet,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Process credit card payment authorization.
     */
    public function processCreditCardPayment(SubscriptionProrationService $prorationService): void
    {
        $rules = [
            'card_number' => ['required', 'string', 'min:12'],
            'card_holder' => ['required', 'string', 'min:3'],
            'card_expiry' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/?([0-9]{2})$/'],
            'card_cvv' => ['required', 'string', 'min:3', 'max:4'],
        ];

        $messages = [
            'card_expiry.regex' => __('Please enter a valid expiry date in MM/YY format.'),
        ];

        if ($this->payment->breakdown['auto_renew'] ?? true) {
            $rules['auto_renew_consent'] = ['accepted'];
            $messages['auto_renew_consent.accepted'] = __('Please confirm your consent for recurring auto-renewal charges.');
        }

        $this->validate($rules, $messages);

        $this->is_processing = true;
        $this->error_message = null;

        try {
            $gatewayRef = 'CC-AUTH-'.strtoupper(bin2hex(random_bytes(4)));

            if ($this->appliedCouponCode) {
                \App\Models\PlatformCoupon::whereNull('operator_id')
                    ->where('code', $this->appliedCouponCode)
                    ->first()
                    ?->incrementUsage();
            }

            $prorationService->completePendingPayment(
                payment: $this->payment,
                gatewayRef: $gatewayRef,
                gateway: 'credit_card'
            );

            session()->flash('success', __(
                'Payment successful! Your account is now upgraded to :plan. All premium features are unlocked.',
                ['plan' => $this->payment->plan->name]
            ));

            $this->redirectRoute('settings.plan', navigate: true);
        } catch (\Throwable $e) {
            $this->is_processing = false;
            $this->error_message = $e->getMessage();
        }
    }

    /**
     * Confirm simulated QRIS / VA / Instant payment.
     */
    public function processSimulatedPayment(SubscriptionProrationService $prorationService): void
    {
        $this->is_processing = true;
        $this->error_message = null;

        try {
            $gatewayRef = strtoupper($this->payment_method).'-SIM-'.strtoupper(bin2hex(random_bytes(4)));

            if ($this->appliedCouponCode) {
                \App\Models\PlatformCoupon::whereNull('operator_id')
                    ->where('code', $this->appliedCouponCode)
                    ->first()
                    ?->incrementUsage();
            }

            $prorationService->completePendingPayment(
                payment: $this->payment,
                gatewayRef: $gatewayRef,
                gateway: $this->payment_method
            );

            session()->flash('success', __(
                'Payment received! Upgraded to :plan tier successfully.',
                ['plan' => $this->payment->plan->name]
            ));

            $this->redirectRoute('settings.plan', navigate: true);
        } catch (\Throwable $e) {
            $this->is_processing = false;
            $this->error_message = $e->getMessage();
        }
    }
}; ?>

<div class="w-full space-y-6 py-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <x-back-link :href="route('settings.plan')">
            {{ __('Back to plan') }}
        </x-back-link>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/70 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-900/60">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                <span>{{ __('Invoice Pending') }} #{{ $payment->invoice_number }}</span>
            </span>
        </div>
    </div>

    @if ($error_message)
        <div class="p-4 rounded-2xl bg-rose-50 text-rose-800 dark:bg-rose-950/70 dark:text-rose-300 border border-rose-200 dark:border-rose-900 text-xs font-semibold flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-base shrink-0"></i>
            <span>{{ $error_message }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Left: Payment Form & Gateway Selection (7 Cols) -->
        <div class="lg:col-span-7 space-y-5">
            <!-- Payment Method Selector -->
            <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                    <div>
                        <h3 class="font-extrabold text-base text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-shield-halved text-purple-600 dark:text-purple-400"></i>
                            {{ __('Select Payment Method') }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Choose how you would like to complete your subscription payment.') }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        wire:click="$set('payment_method', 'cc')"
                        class="p-3.5 rounded-2xl border text-left flex items-start gap-3 transition-all cursor-pointer {{ $payment_method === 'cc' ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-950/40 text-purple-900 dark:text-white ring-2 ring-purple-600 shadow-xs' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}"
                    >
                        <i class="fa-solid fa-credit-card text-lg text-purple-600 dark:text-purple-400 mt-0.5"></i>
                        <div>
                            <span class="font-black text-xs block">{{ __('Credit / Debit Card') }}</span>
                            <span class="text-[11px] text-slate-400 block mt-0.5">Visa / Mastercard / JCB</span>
                        </div>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('payment_method', 'qris')"
                        class="p-3.5 rounded-2xl border text-left flex items-start gap-3 transition-all cursor-pointer {{ $payment_method === 'qris' ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-950/40 text-purple-900 dark:text-white ring-2 ring-purple-600 shadow-xs' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}"
                    >
                        <i class="fa-solid fa-qrcode text-lg text-purple-600 dark:text-purple-400 mt-0.5"></i>
                        <div>
                            <span class="font-black text-xs block">{{ __('QRIS Instant') }}</span>
                            <span class="text-[11px] text-slate-400 block mt-0.5">GoPay / OVO / BCA / Dana</span>
                        </div>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('payment_method', 'va')"
                        class="p-3.5 rounded-2xl border text-left flex items-start gap-3 transition-all cursor-pointer {{ $payment_method === 'va' ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-950/40 text-purple-900 dark:text-white ring-2 ring-purple-600 shadow-xs' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}"
                    >
                        <i class="fa-solid fa-building-columns text-lg text-purple-600 dark:text-purple-400 mt-0.5"></i>
                        <div>
                            <span class="font-black text-xs block">{{ __('Virtual Account') }}</span>
                            <span class="text-[11px] text-slate-400 block mt-0.5">BCA / Mandiri / BRI / BNI</span>
                        </div>
                    </button>

                    <button
                        type="button"
                        wire:click="$set('payment_method', 'direct')"
                        class="p-3.5 rounded-2xl border text-left flex items-start gap-3 transition-all cursor-pointer {{ $payment_method === 'direct' ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-950/40 text-purple-900 dark:text-white ring-2 ring-purple-600 shadow-xs' : 'border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-700 dark:text-slate-300' }}"
                    >
                        <i class="fa-solid fa-bolt text-lg text-amber-500 mt-0.5"></i>
                        <div>
                            <span class="font-black text-xs block">{{ __('Instant Sandbox Test') }}</span>
                            <span class="text-[11px] text-slate-400 block mt-0.5">1-Click Test Activation</span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Active Form Content based on method -->
            @if ($payment_method === 'cc')
                <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                        <h4 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-credit-card text-purple-600"></i>
                            {{ __('Card Information') }}
                        </h4>
                        <div class="flex items-center gap-2 text-slate-400 text-base">
                            <i class="fa-brands fa-cc-visa"></i>
                            <i class="fa-brands fa-cc-mastercard"></i>
                            <i class="fa-brands fa-cc-jcb"></i>
                        </div>
                    </div>

                    <form wire:submit="processCreditCardPayment" class="space-y-4">
                        <div>
                            <x-label for="card_holder" :value="__('Cardholder Name')" required />
                            <x-input id="card_holder" type="text" wire:model="card_holder" placeholder="John Doe" :error="$errors->has('card_holder')" />
                            <x-input-error :messages="$errors->get('card_holder')" />
                        </div>

                        <div>
                            <x-label for="card_number" :value="__('Card Number')" required />
                            <div class="relative">
                                <i class="fa-solid fa-credit-card absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <x-input id="card_number" type="text" wire:model="card_number" placeholder="4000 1234 5678 9010" class="pl-9 font-mono" maxlength="19" :error="$errors->has('card_number')" />
                            </div>
                            <x-input-error :messages="$errors->get('card_number')" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-label for="card_expiry" :value="__('Expiration Date')" required />
                                <x-input id="card_expiry" type="text" wire:model="card_expiry" placeholder="MM/YY" class="font-mono text-center" maxlength="5" :error="$errors->has('card_expiry')" />
                                <x-input-error :messages="$errors->get('card_expiry')" />
                            </div>
                            <div>
                                <x-label for="card_cvv" :value="__('Security Code (CVV)')" required />
                                <x-input id="card_cvv" type="password" wire:model="card_cvv" placeholder="123" class="font-mono text-center" maxlength="4" :error="$errors->has('card_cvv')" />
                                <x-input-error :messages="$errors->get('card_cvv')" />
                            </div>
                        </div>

                        @if ($payment->breakdown['auto_renew'] ?? true)
                            <div class="p-3.5 rounded-2xl bg-purple-50/70 dark:bg-purple-950/30 border border-purple-200/80 dark:border-purple-900/50 text-xs space-y-1.5">
                                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        wire:model="auto_renew_consent"
                                        class="mt-0.5 rounded border-purple-300 text-purple-600 focus:ring-purple-500 dark:border-purple-800 dark:bg-zinc-900"
                                    />
                                    <span class="text-xs text-purple-900 dark:text-purple-200 font-semibold leading-tight">
                                        {{ __('I consent to recurring auto-renewal charges at the end of each billing cycle until cancelled.') }}
                                    </span>
                                </label>
                                @error('auto_renew_consent')
                                    <p class="text-[11px] font-bold text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <div class="pt-3">
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="w-full h-11 rounded-2xl bg-purple-600 hover:bg-purple-700 active:bg-purple-800 text-white font-extrabold text-sm shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                            >
                                <span wire:loading.remove class="flex items-center gap-2">
                                    <i class="fa-solid fa-lock text-xs"></i>
                                    <span>{{ __('Authorize & Pay Rp :amount', ['amount' => number_format((float) $payment->net_amount_paid, 0, ',', '.')]) }}</span>
                                </span>
                                <span wire:loading class="flex items-center gap-2">
                                    <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                    <span>{{ __('Processing Card Authorization...') }}</span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            @elseif ($payment_method === 'qris')
                <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4 text-center">
                    <div class="inline-flex p-4 rounded-3xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 shadow-inner">
                        <!-- Simulated QRIS Matrix -->
                        <div class="w-48 h-48 bg-slate-900 dark:bg-white rounded-2xl flex flex-col items-center justify-center p-3 text-white dark:text-slate-900">
                            <i class="fa-solid fa-qrcode text-7xl"></i>
                            <span class="text-[10px] font-mono font-black mt-2 tracking-widest uppercase">QRIS STANDAR INDONESIA</span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <h4 class="font-black text-sm text-slate-900 dark:text-white">{{ __('Scan QRIS to Pay') }}</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Open BCA Mobile, GoPay, OVO, Dana, or any banking app supporting QRIS.') }}
                        </p>
                    </div>

                    <div class="pt-2">
                        <button
                            type="button"
                            wire:click="processSimulatedPayment"
                            wire:loading.attr="disabled"
                            class="w-full h-11 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-sm shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <i class="fa-solid fa-circle-check text-xs"></i>
                            <span>{{ __('I Have Completed QRIS Payment (Simulate)') }}</span>
                        </button>
                    </div>
                </div>
            @elseif ($payment_method === 'va')
                <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Choose Virtual Account Bank') }}</label>
                        <select wire:model.live="va_bank" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-xs font-bold text-slate-900 dark:text-white">
                            <option value="BCA">BCA Virtual Account</option>
                            <option value="Mandiri">Mandiri Virtual Account</option>
                            <option value="BRI">BRI Virtual Account (BRIVA)</option>
                            <option value="BNI">BNI Virtual Account</option>
                        </select>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200/80 dark:border-zinc-700/80 space-y-2">
                        <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">{{ $va_bank }} Virtual Account Number</span>
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-black font-mono text-purple-700 dark:text-purple-300">
                                8800 9182 3910 2819
                            </span>
                            <span class="text-xs text-slate-400 font-semibold">{{ __('Copy') }}</span>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button
                            type="button"
                            wire:click="processSimulatedPayment"
                            wire:loading.attr="disabled"
                            class="w-full h-11 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-sm shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <i class="fa-solid fa-circle-check text-xs"></i>
                            <span>{{ __('Simulate VA Transfer Success') }}</span>
                        </button>
                    </div>
                </div>
            @else
                <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4 text-center">
                    <div class="w-14 h-14 rounded-3xl bg-amber-50 dark:bg-amber-950/70 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl mx-auto">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <div class="space-y-1">
                        <h4 class="font-black text-base text-slate-900 dark:text-white">{{ __('Fast Sandbox Activation') }}</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Instantly approve this subscription payment in sandbox mode without entering card credentials.') }}
                        </p>
                    </div>
                    <div class="pt-2">
                        <button
                            type="button"
                            wire:click="processSimulatedPayment"
                            wire:loading.attr="disabled"
                            class="w-full h-11 rounded-2xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-sm shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <i class="fa-solid fa-bolt text-xs"></i>
                            <span>{{ __('Activate :plan Instantly', ['plan' => $payment->plan->name]) }}</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <!-- Right: Order Summary & Proration Card (5 Cols) -->
        <div class="lg:col-span-5 space-y-4">
            <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                    <h3 class="font-extrabold text-base text-slate-900 dark:text-white">
                        {{ __('Order Summary') }}
                    </h3>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black uppercase bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                        {{ $payment->plan->name }}
                    </span>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between font-medium text-slate-600 dark:text-slate-400">
                        <span>{{ __('Billing Cycle') }}</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ ucfirst($payment->billing_interval) }}</span>
                    </div>

                    <div class="flex items-center justify-between font-medium text-slate-600 dark:text-slate-400">
                        <span>{{ __('Renewal Mode') }}</span>
                        <span class="font-bold text-slate-900 dark:text-white">
                            {{ ($payment->breakdown['auto_renew'] ?? true) ? __('Auto-Renewing (Recurring)') : __('One-Time Term Payment') }}
                        </span>
                    </div>

                    @if (($payment->breakdown['unused_credit'] ?? 0) > 0)
                        <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                            <span>{{ __('Unused Current Plan Credit') }}</span>
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                - Rp {{ number_format((float) $payment->breakdown['unused_credit'], 0, ',', '.') }}
                            </span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between text-slate-600 dark:text-slate-300">
                        <span>{{ __(':plan Prorated Charge', ['plan' => $payment->plan->name]) }}</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white">
                            + Rp {{ number_format((float) $payment->gross_amount, 0, ',', '.') }}
                        </span>
                    </div>

                    @if ($discountAmount > 0)
                        <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                            <span class="flex items-center gap-1.5 font-bold">
                                <i class="fa-solid fa-tag text-[10px]"></i>
                                <span>{{ __('Subscription Promo (:code)', ['code' => $appliedCouponCode]) }}</span>
                            </span>
                            <span class="font-mono font-bold">
                                - Rp {{ number_format($discountAmount, 0, ',', '.') }}
                            </span>
                        </div>
                    @endif

                    <!-- Subscription Promo Code Input Accordion -->
                    <div class="pt-2 border-t border-slate-100 dark:border-zinc-800 space-y-1.5" x-data="{ open: @json($appliedCouponCode || $couponMessage ? true : false) }">
                        <div class="flex items-center justify-between">
                            <button
                                type="button"
                                @click="open = !open"
                                class="text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline flex items-center gap-1.5 cursor-pointer"
                            >
                                <i class="fa-solid fa-ticket text-[11px]"></i>
                                <span>{{ __('Have a platform promo code?') }}</span>
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
                                        placeholder="{{ __('ENTER PROMO CODE') }}"
                                        class="flex-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-xs font-mono uppercase font-black text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                    />
                                    <button
                                        type="button"
                                        wire:click="applyCoupon"
                                        wire:loading.attr="disabled"
                                        wire:target="applyCoupon"
                                        class="h-9 px-3.5 rounded-xl bg-purple-600 hover:bg-purple-700 active:bg-purple-800 text-white text-xs font-bold transition shadow-xs cursor-pointer shrink-0 disabled:opacity-50 flex items-center gap-1.5"
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

                    <div class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                        <div>
                            <span class="font-black text-sm text-slate-900 dark:text-white block">{{ __('Total Amount Due') }}</span>
                            <span class="text-[11px] text-slate-400">{{ __('Includes all tax & fees') }}</span>
                        </div>
                        <span class="text-2xl font-black font-mono text-purple-600 dark:text-purple-400">
                            Rp {{ number_format((float) $payment->net_amount_paid, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/80 dark:border-zinc-800 space-y-2 text-xs">
                    <span class="font-bold text-slate-800 dark:text-slate-200 block">{{ __('Included in :plan:', ['plan' => $payment->plan->name]) }}</span>
                    <ul class="space-y-1 text-slate-500 dark:text-slate-400">
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-emerald-500 text-[10px]"></i>
                            <span>{{ $payment->plan->listingLimitLabel() }}</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-emerald-500 text-[10px]"></i>
                            <span>{{ $payment->plan->teamSeatLabel() }}</span>
                        </li>
                        @if ($payment->plan->hasFeature('custom_domain'))
                            <li class="flex items-center gap-2">
                                <i class="fa-solid fa-check text-emerald-500 text-[10px]"></i>
                                <span>{{ __('Your own website address') }}</span>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
