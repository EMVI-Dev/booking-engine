<?php

use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\SubscriptionPayment;
use App\Services\DokuPaymentService;
use App\Services\SubscriptionProrationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Subscription Checkout')] #[Layout('layouts.app')] class extends Component
{
    public SubscriptionPayment $payment;

    public string $payment_method = 'doku'; // 'doku' | 'cc' | 'qris' | 'va' | 'direct'

    // Gateway & Environment state
    public bool $simulator_enabled = false;

    public bool $doku_configured = false;

    // Credit Card Form Fields (Sandbox simulator only)
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
        $this->simulator_enabled = DokuPaymentService::simulatorEnabled();
        $this->doku_configured = DokuPaymentService::isConfigured();

        if ($this->doku_configured) {
            $this->payment_method = 'doku';
        } elseif ($this->simulator_enabled) {
            $this->payment_method = 'cc';
        } else {
            $this->payment_method = 'doku';
        }

        $this->card_holder = (string) (auth()->user()?->name ?? '');

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
        $coupon = PlatformCoupon::whereNull('operator_id')
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
     * Redirect to DOKU Jokul Hosted Checkout for real payment processing.
     */
    public function payWithDoku(DokuPaymentService $dokuService, SubscriptionProrationService $prorationService): void
    {
        $this->is_processing = true;
        $this->error_message = null;

        if ($this->payment->net_amount_paid <= 0) {
            $this->completeZeroAmountPayment($prorationService);

            return;
        }

        try {
            $url = $dokuService->createSubscriptionCheckoutSession($this->payment);

            if ($url) {
                $this->redirect($url);

                return;
            }

            throw new Exception(__('Unable to initialize payment session. Please check gateway configuration.'));
        } catch (Throwable $e) {
            $this->is_processing = false;
            $this->error_message = $e->getMessage();
        }
    }

    /**
     * Activate a zero-amount subscription invoice covered entirely by credits or coupons.
     */
    public function completeZeroAmountPayment(SubscriptionProrationService $prorationService): void
    {
        if ($this->payment->net_amount_paid > 0) {
            abort(400, __('Payment required.'));
        }

        $this->is_processing = true;

        try {
            if ($this->appliedCouponCode) {
                PlatformCoupon::whereNull('operator_id')
                    ->where('code', $this->appliedCouponCode)
                    ->first()
                    ?->incrementUsage();
            }

            $prorationService->completePendingPayment(
                payment: $this->payment,
                gatewayRef: 'FREE-ACTIVATION-'.strtoupper(bin2hex(random_bytes(4))),
                gateway: 'free'
            );

            session()->flash('success', __(
                'Subscription activated! Upgraded to :plan tier successfully.',
                ['plan' => $this->payment->plan->name]
            ));

            $this->redirectRoute('settings.plan', navigate: true);
        } catch (Throwable $e) {
            $this->is_processing = false;
            $this->error_message = $e->getMessage();
        }
    }

    /**
     * Process credit card payment authorization (Only available in sandbox/simulator mode).
     */
    public function processCreditCardPayment(SubscriptionProrationService $prorationService, DokuPaymentService $dokuService): void
    {
        if (! DokuPaymentService::simulatorEnabled()) {
            $this->payWithDoku($dokuService, $prorationService);

            return;
        }

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
                PlatformCoupon::whereNull('operator_id')
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
        } catch (Throwable $e) {
            $this->is_processing = false;
            $this->error_message = $e->getMessage();
        }
    }

    /**
     * Confirm simulated QRIS / VA / Instant payment.
     */
    public function processSimulatedPayment(SubscriptionProrationService $prorationService): void
    {
        if (! DokuPaymentService::simulatorEnabled()) {
            abort(403, __('Payment simulation is disabled in production.'));
        }

        $this->is_processing = true;
        $this->error_message = null;

        try {
            $gatewayRef = strtoupper($this->payment_method).'-SIM-'.strtoupper(bin2hex(random_bytes(4)));

            if ($this->appliedCouponCode) {
                PlatformCoupon::whereNull('operator_id')
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
        } catch (Throwable $e) {
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
            @if ($payment->net_amount_paid <= 0)
                <div class="p-8 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs text-center space-y-5">
                    <div class="w-16 h-16 rounded-3xl bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-3xl mx-auto shadow-inner">
                        <i class="fa-solid fa-gift"></i>
                    </div>
                    <div class="space-y-1.5 max-w-md mx-auto">
                        <h3 class="font-extrabold text-lg text-slate-900 dark:text-white">{{ __('Zero Amount Due') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Your unused plan credit or promotional discount covers 100% of this invoice. No payment gateway or credit card charge required.') }}
                        </p>
                    </div>
                    <div class="pt-2">
                        <button
                            type="button"
                            wire:click="completeZeroAmountPayment"
                            wire:loading.attr="disabled"
                            class="w-full h-12 rounded-2xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-extrabold text-sm shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="completeZeroAmountPayment" class="flex items-center gap-2">
                                <i class="fa-solid fa-circle-check text-sm"></i>
                                <span>{{ __('Activate :plan Tier Now', ['plan' => $payment->plan->name]) }}</span>
                            </span>
                            <span wire:loading wire:target="completeZeroAmountPayment" class="flex items-center gap-2">
                                <i class="fa-solid fa-circle-notch fa-spin text-sm"></i>
                                <span>{{ __('Activating Subscription...') }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            @else
                @if ($doku_configured)
                    <!-- DOKU Hosted Payment Gateway -->
                    <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-5">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800">
                            <div>
                                <h3 class="font-extrabold text-base text-slate-900 dark:text-white flex items-center gap-2">
                                    <i class="fa-solid fa-shield-halved text-purple-600 dark:text-purple-400"></i>
                                    {{ __('DOKU Secure Checkout') }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ __('Pay securely via DOKU Jokul Payment Gateway with instant activation.') }}
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-purple-50 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300 border border-purple-200/70 dark:border-purple-800/70">
                                <i class="fa-solid fa-lock text-[9px]"></i>
                                <span>{{ __('PCI-DSS Verified') }}</span>
                            </span>
                        </div>

                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-200/70 dark:border-zinc-700/60 space-y-3">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 block">{{ __('Payment Channels Supported:') }}</span>
                            <div class="grid grid-cols-2 gap-2 text-xs font-bold text-slate-600 dark:text-slate-300">
                                <span class="p-2 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 shadow-2xs flex items-center gap-2">
                                    <i class="fa-solid fa-credit-card text-purple-600"></i>
                                    <span class="truncate">Visa / Mastercard / JCB</span>
                                </span>
                                <span class="p-2 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 shadow-2xs flex items-center gap-2">
                                    <i class="fa-solid fa-qrcode text-emerald-600"></i>
                                    <span class="truncate">QRIS Instant</span>
                                </span>
                                <span class="p-2 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 shadow-2xs flex items-center gap-2">
                                    <i class="fa-solid fa-building-columns text-blue-600"></i>
                                    <span class="truncate">BCA, Mandiri, BRI, BNI</span>
                                </span>
                                <span class="p-2 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 shadow-2xs flex items-center gap-2">
                                    <i class="fa-solid fa-wallet text-amber-600"></i>
                                    <span class="truncate">OVO, ShopeePay</span>
                                </span>
                            </div>
                        </div>

                        @if ($payment->breakdown['auto_renew'] ?? true)
                            <div class="p-3.5 rounded-2xl bg-purple-50/70 dark:bg-purple-950/30 border border-purple-200/80 dark:border-purple-900/50 text-xs">
                                <span class="text-xs text-purple-900 dark:text-purple-200 font-semibold leading-tight flex items-start gap-2">
                                    <i class="fa-solid fa-rotate text-purple-600 dark:text-purple-400 mt-0.5 shrink-0"></i>
                                    <span>{{ __('Your plan renewal will be calculated at each interval and billed securely.') }}</span>
                                </span>
                            </div>
                        @endif

                        <div class="pt-2">
                            <button
                                type="button"
                                wire:click="payWithDoku"
                                wire:loading.attr="disabled"
                                class="w-full h-12 rounded-2xl bg-purple-600 hover:bg-purple-700 active:bg-purple-800 text-white font-black text-sm shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="payWithDoku" class="flex items-center gap-2">
                                    <i class="fa-solid fa-lock text-xs"></i>
                                    <span>{{ __('Pay Rp :amount via DOKU Secure Gateway', ['amount' => number_format((float) $payment->net_amount_paid, 0, ',', '.')]) }}</span>
                                </span>
                                <span wire:loading wire:target="payWithDoku" class="flex items-center gap-2">
                                    <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                    <span>{{ __('Connecting to Gateway...') }}</span>
                                </span>
                            </button>
                        </div>
                    </div>
                @endif

                @if ($simulator_enabled)
                    <!-- Sandbox Testing Controls (Visible only in local / sandbox / test environments) -->
                    <div class="p-6 rounded-3xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-900/40 shadow-xs space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-amber-200/60 dark:border-amber-900/50">
                            <div>
                                <h3 class="font-extrabold text-sm text-amber-950 dark:text-amber-300 flex items-center gap-2">
                                    <i class="fa-solid fa-vial text-amber-600 dark:text-amber-400"></i>
                                    <span>{{ __('Developer Sandbox Simulator') }}</span>
                                </h3>
                                <p class="text-[11px] text-amber-800/80 dark:text-amber-400/80 mt-0.5">
                                    {{ __('Active in testing environments only. Hidden automatically in live production.') }}
                                </p>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-200/60 text-amber-900 dark:bg-amber-900/60 dark:text-amber-300">
                                {{ __('Sandbox Mode') }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                wire:click="$set('payment_method', 'direct')"
                                class="p-2.5 rounded-xl border text-left text-xs font-bold transition cursor-pointer {{ $payment_method === 'direct' ? 'border-amber-600 bg-amber-100/60 dark:bg-amber-950/60 text-amber-950 dark:text-amber-200' : 'border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-slate-700 dark:text-slate-300' }}"
                            >
                                <i class="fa-solid fa-bolt text-amber-600 mr-1"></i>
                                <span>{{ __('1-Click Test Activation') }}</span>
                            </button>
                            <button
                                type="button"
                                wire:click="$set('payment_method', 'cc')"
                                class="p-2.5 rounded-xl border text-left text-xs font-bold transition cursor-pointer {{ $payment_method === 'cc' ? 'border-amber-600 bg-amber-100/60 dark:bg-amber-950/60 text-amber-950 dark:text-amber-200' : 'border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-slate-700 dark:text-slate-300' }}"
                            >
                                <i class="fa-solid fa-credit-card text-amber-600 mr-1"></i>
                                <span>{{ __('Simulated Card Form') }}</span>
                            </button>
                        </div>

                        @if ($payment_method === 'direct')
                            <div class="pt-1">
                                <button
                                    type="button"
                                    wire:click="processSimulatedPayment"
                                    wire:loading.attr="disabled"
                                    class="w-full h-11 rounded-2xl bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-slate-950 font-black text-xs shadow-xs transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                                >
                                    <i class="fa-solid fa-bolt text-xs"></i>
                                    <span>{{ __('Simulate 1-Click Upgrade (:plan)', ['plan' => $payment->plan->name]) }}</span>
                                </button>
                            </div>
                        @elseif ($payment_method === 'cc')
                            <div class="flex items-center justify-between pb-2 border-b border-amber-200/60 dark:border-amber-900/40">
                                <h4 class="font-bold text-xs text-slate-900 dark:text-white flex items-center gap-2">
                                    <i class="fa-solid fa-credit-card text-purple-600"></i>
                                    <span>{{ __('Card Information') }}</span>
                                </h4>
                                <div class="flex items-center gap-1.5 text-slate-400 text-xs">
                                    <i class="fa-brands fa-cc-visa"></i>
                                    <i class="fa-brands fa-cc-mastercard"></i>
                                    <i class="fa-brands fa-cc-jcb"></i>
                                </div>
                            </div>

                            <form wire:submit="processCreditCardPayment" class="space-y-3 pt-1">
                                <div>
                                    <x-label for="card_holder" :value="__('Cardholder Name')" required />
                                    <x-input id="card_holder" type="text" wire:model="card_holder" placeholder="John Doe" :error="$errors->has('card_holder')" />
                                    <x-input-error :messages="$errors->get('card_holder')" />
                                </div>
                                <div>
                                    <x-label for="card_number" :value="__('Card Number')" required />
                                    <x-input id="card_number" type="text" wire:model="card_number" placeholder="4000 1234 5678 9010" class="font-mono" maxlength="19" :error="$errors->has('card_number')" />
                                    <x-input-error :messages="$errors->get('card_number')" />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <x-label for="card_expiry" :value="__('Expiration Date')" required />
                                        <x-input id="card_expiry" type="text" wire:model="card_expiry" placeholder="MM/YY" class="font-mono text-center" maxlength="5" :error="$errors->has('card_expiry')" />
                                        <x-input-error :messages="$errors->get('card_expiry')" />
                                    </div>
                                    <div>
                                        <x-label for="card_cvv" :value="__('CVV')" required />
                                        <x-input id="card_cvv" type="password" wire:model="card_cvv" placeholder="123" class="font-mono text-center" maxlength="4" :error="$errors->has('card_cvv')" />
                                        <x-input-error :messages="$errors->get('card_cvv')" />
                                    </div>
                                </div>
                                <button
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    class="w-full h-11 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-black text-xs shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                                >
                                    <i class="fa-solid fa-lock text-xs"></i>
                                    <span>{{ __('Authorize Test Card Charge') }}</span>
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                @if (! $doku_configured && ! $simulator_enabled)
                    <div class="p-6 rounded-3xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-center space-y-3">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 text-2xl"></i>
                        <h4 class="font-bold text-sm text-amber-900 dark:text-amber-200">{{ __('Payment Gateway Offline') }}</h4>
                        <p class="text-xs text-amber-800 dark:text-amber-300 max-w-sm mx-auto">
                            {{ __('The platform payment gateway is currently being configured. Please contact platform support.') }}
                        </p>
                    </div>
                @endif
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
