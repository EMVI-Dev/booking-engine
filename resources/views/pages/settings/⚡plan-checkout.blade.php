<?php

use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\SubscriptionPayment;
use App\Services\DokuPaymentService;
use App\Services\SubscriptionProrationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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

    #[Locked]
    public ?string $appliedCouponCode = null;

    #[Locked]
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

        if ($payment->status === SubscriptionPayment::STATUS_PENDING) {
            app(DokuPaymentService::class)->syncSubscriptionPaymentStatus($payment);
            $payment->refresh();
        }

        if ($payment->status === SubscriptionPayment::STATUS_COMPLETED) {
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
        if ($this->payment->status !== SubscriptionPayment::STATUS_PENDING) {
            return;
        }

        $cleanCode = strtoupper(trim($this->couponCode));

        if (empty($cleanCode)) {
            $this->couponMessage = __('Please enter a promo code.');
            $this->couponValid = false;

            return;
        }

        $operator = auth()->user()?->currentOperator();

        // Match platform-wide subscription coupons (operator_id = null)
        // or coupons targeted specifically to this operator.
        $coupon = PlatformCoupon::findForSubscription($cleanCode, $operator);

        if (! $coupon) {
            $this->couponMessage = __('Invalid subscription promo code.');
            $this->couponValid = false;
            $this->removeCoupon();

            return;
        }

        $gross = (float) $this->payment->gross_amount;
        $result = $coupon->validateFor($gross, $operator?->id);

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
        if ($this->payment->status !== SubscriptionPayment::STATUS_PENDING) {
            return;
        }

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
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[4px] text-xs font-medium bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                <span>{{ __('Invoice Pending') }} #{{ $payment->invoice_number }}</span>
            </span>
        </div>
    </div>

    @if ($error_message)
        <div class="p-3.5 rounded-[8px] bg-rose-50 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-900/60 text-xs font-medium flex items-center gap-2 shadow-none">
            <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm shrink-0"></i>
            <span>{{ $error_message }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Left: Payment Form & Gateway Selection (7 Cols) -->
        <div class="lg:col-span-7 space-y-5">
            @if ($payment->net_amount_paid <= 0)
                <div class="p-8 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none text-center space-y-5">
                    <div class="w-14 h-14 rounded-[8px] bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl mx-auto shadow-none">
                        <i class="fa-solid fa-gift"></i>
                    </div>
                    <div class="space-y-1.5 max-w-md mx-auto">
                        <h3 class="font-semibold text-lg text-[#12181E] dark:text-white">{{ __('Zero Amount Due') }}</h3>
                        <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __('Your unused plan credit or promotional discount covers 100% of this invoice. No payment gateway or credit card charge required.') }}
                        </p>
                    </div>
                    <div class="pt-2">
                        <button
                            type="button"
                            wire:click="completeZeroAmountPayment"
                            wire:loading.attr="disabled"
                            class="w-full h-10 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:bg-[#E5D735] text-[#12181E] font-semibold text-xs shadow-none transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="completeZeroAmountPayment" class="flex items-center gap-2">
                                <i class="fa-solid fa-circle-check text-xs"></i>
                                <span>{{ __('Activate :plan Tier Now', ['plan' => $payment->plan->name]) }}</span>
                            </span>
                            <span wire:loading wire:target="completeZeroAmountPayment" class="flex items-center gap-2">
                                <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                <span>{{ __('Activating Subscription...') }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            @else
                @if ($doku_configured)
                    <!-- DOKU Hosted Payment Gateway -->
                    <div class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-5">
                        <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                            <div>
                                <h3 class="font-semibold text-base text-[#12181E] dark:text-white flex items-center gap-2">
                                    <i class="fa-solid fa-shield-halved text-purple-600 dark:text-purple-400"></i>
                                    {{ __('DOKU Secure Checkout') }}
                                </h3>
                                <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] mt-0.5">
                                    {{ __('Pay securely via DOKU Jokul Payment Gateway with instant activation.') }}
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-[4px] text-[10px] font-medium uppercase bg-[#F8F9FA] dark:bg-[#141821] text-[#5A6578] dark:text-[#9DA4B2] border border-[#E4E5E9] dark:border-[#1E2433]">
                                <i class="fa-solid fa-lock text-[9px]"></i>
                                <span>{{ __('PCI-DSS Verified') }}</span>
                            </span>
                        </div>

                        <div class="p-3.5 rounded-[8px] bg-[#F8F9FA] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] text-xs text-[#5A6578] dark:text-[#9DA4B2] flex items-center gap-2">
                            <i class="fa-solid fa-circle-info text-[#5A6578] shrink-0"></i>
                            <span>{{ __('You will choose your payment method on the DOKU secure page — Credit Card, QRIS, Virtual Account, and e-wallets are all available.') }}</span>
                        </div>

                        @if ($payment->breakdown['auto_renew'] ?? true)
                            <div class="p-3.5 rounded-[8px] bg-[#F8F9FA] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] text-xs">
                                <span class="text-xs text-[#12181E] dark:text-white font-medium leading-tight flex items-start gap-2">
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
                                class="w-full h-10 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:bg-[#E5D735] text-[#12181E] font-semibold text-xs shadow-none transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
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
                    <div class="p-5 sm:p-6 rounded-[12px] bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-900/40 shadow-none space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-amber-200/60 dark:border-amber-900/50">
                            <div>
                                <h3 class="font-semibold text-sm text-amber-950 dark:text-amber-300 flex items-center gap-2">
                                    <i class="fa-solid fa-vial text-amber-600 dark:text-amber-400"></i>
                                    <span>{{ __('Developer Sandbox Simulator') }}</span>
                                </h3>
                                <p class="text-[11px] text-amber-800/80 dark:text-amber-400/80 mt-0.5">
                                    {{ __('Active in testing environments only. Hidden automatically in live production.') }}
                                </p>
                            </div>
                            <span class="px-2 py-0.5 rounded-[4px] text-[10px] font-medium uppercase bg-amber-200/60 text-amber-900 dark:bg-amber-900/60 dark:text-amber-300">
                                {{ __('Sandbox Mode') }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                wire:click="$set('payment_method', 'direct')"
                                class="p-2.5 rounded-[6px] border text-left text-xs font-medium transition cursor-pointer shadow-none {{ $payment_method === 'direct' ? 'border-amber-600 bg-amber-100/60 dark:bg-amber-950/60 text-amber-950 dark:text-amber-200' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-[#5A6578] dark:text-[#9DA4B2]' }}"
                            >
                                <i class="fa-solid fa-bolt text-amber-600 mr-1"></i>
                                <span>{{ __('1-Click Test Activation') }}</span>
                            </button>
                            <button
                                type="button"
                                wire:click="$set('payment_method', 'cc')"
                                class="p-2.5 rounded-[6px] border text-left text-xs font-medium transition cursor-pointer shadow-none {{ $payment_method === 'cc' ? 'border-amber-600 bg-amber-100/60 dark:bg-amber-950/60 text-amber-950 dark:text-amber-200' : 'border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-[#5A6578] dark:text-[#9DA4B2]' }}"
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
                                    class="w-full h-10 rounded-[6px] bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-[#12181E] font-semibold text-xs shadow-none transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                                >
                                    <i class="fa-solid fa-bolt text-xs"></i>
                                    <span>{{ __('Simulate 1-Click Upgrade (:plan)', ['plan' => $payment->plan->name]) }}</span>
                                </button>
                            </div>
                        @elseif ($payment_method === 'cc')
                            <div class="flex items-center justify-between pb-2 border-b border-amber-200/60 dark:border-amber-900/40">
                                <h4 class="font-semibold text-xs text-[#12181E] dark:text-white flex items-center gap-2">
                                    <i class="fa-solid fa-credit-card text-purple-600"></i>
                                    <span>{{ __('Card Information') }}</span>
                                </h4>
                                <div class="flex items-center gap-1.5 text-[#5A6578] dark:text-[#9DA4B2] text-xs">
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
                                    class="w-full h-10 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:bg-[#E5D735] text-[#12181E] font-semibold text-xs shadow-none transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                                >
                                    <i class="fa-solid fa-lock text-xs"></i>
                                    <span>{{ __('Authorize Test Card Charge') }}</span>
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                @if (! $doku_configured && ! $simulator_enabled)
                    <div class="p-6 rounded-[12px] bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-center space-y-3 shadow-none">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 text-2xl"></i>
                        <h4 class="font-semibold text-sm text-amber-900 dark:text-amber-200">{{ __('Payment Gateway Offline') }}</h4>
                        <p class="text-xs text-amber-800 dark:text-amber-300 max-w-sm mx-auto">
                            {{ __('The platform payment gateway is currently being configured. Please contact platform support.') }}
                        </p>
                    </div>
                @endif
            @endif
        </div>

        <!-- Right: Order Summary & Proration Card (5 Cols) -->
        <div class="lg:col-span-5 space-y-4">
            <div class="p-5 sm:p-6 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <h3 class="font-semibold text-base text-[#12181E] dark:text-white">
                        {{ __('Order Summary') }}
                    </h3>
                    <span class="px-2 py-0.5 rounded-[4px] text-[10px] font-semibold uppercase bg-[#FFEF4D] text-[#12181E]">
                        {{ $payment->plan->name }}
                    </span>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between font-medium text-[#5A6578] dark:text-[#9DA4B2]">
                        <span>{{ __('Billing Cycle') }}</span>
                        <span class="font-semibold text-[#12181E] dark:text-white">{{ ucfirst($payment->billing_interval) }}</span>
                    </div>

                    <div class="flex items-center justify-between font-medium text-[#5A6578] dark:text-[#9DA4B2]">
                        <span>{{ __('Renewal Mode') }}</span>
                        <span class="font-semibold text-[#12181E] dark:text-white">
                            {{ ($payment->breakdown['auto_renew'] ?? true) ? __('Auto-Renewing (Recurring)') : __('One-Time Term Payment') }}
                        </span>
                    </div>

                    @if (($payment->breakdown['unused_credit'] ?? 0) > 0)
                        <div class="flex items-center justify-between text-[#5A6578] dark:text-[#9DA4B2]">
                            <span>{{ __('Unused Current Plan Credit') }}</span>
                            <span class="font-mono font-medium text-emerald-600 dark:text-emerald-400">
                                - Rp {{ number_format((float) $payment->breakdown['unused_credit'], 0, ',', '.') }}
                            </span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between text-[#5A6578] dark:text-[#9DA4B2]">
                        <span>{{ __(':plan Prorated Charge', ['plan' => $payment->plan->name]) }}</span>
                        <span class="font-mono font-medium text-[#12181E] dark:text-white">
                            + Rp {{ number_format((float) $payment->gross_amount, 0, ',', '.') }}
                        </span>
                    </div>

                    @if ($discountAmount > 0)
                        <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                            <span class="flex items-center gap-1.5 font-medium">
                                <i class="fa-solid fa-tag text-[10px]"></i>
                                <span>{{ __('Subscription Promo (:code)', ['code' => $appliedCouponCode]) }}</span>
                            </span>
                            <span class="font-mono font-medium">
                                - Rp {{ number_format($discountAmount, 0, ',', '.') }}
                            </span>
                        </div>
                    @endif

                    <!-- Subscription Promo Code Input Accordion -->
                    <div class="pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433] space-y-1.5" x-data="{ open: @json($appliedCouponCode || $couponMessage ? true : false) }">
                        <div class="flex items-center justify-between">
                            <button
                                type="button"
                                @click="open = !open"
                                class="text-xs font-medium text-[#12181E] dark:text-white hover:underline flex items-center gap-1.5 cursor-pointer"
                            >
                                <i class="fa-solid fa-ticket text-[11px] text-[#5A6578] dark:text-[#9DA4B2]"></i>
                                <span>{{ __('Have a platform promo code?') }}</span>
                                <i class="fa-solid fa-chevron-down text-[9px] transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                            </button>
                            @if ($appliedCouponCode)
                                <span class="text-[10px] font-medium uppercase text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/60 px-2 py-0.5 rounded-[4px]">
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
                                        class="flex-1 px-3 py-1.5 rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-xs font-mono uppercase font-semibold text-[#12181E] dark:text-white placeholder:text-[#5A6578] focus:border-[#12181E] dark:focus:border-white focus:outline-none shadow-none"
                                    />
                                    <button
                                        type="button"
                                        wire:click="applyCoupon"
                                        wire:loading.attr="disabled"
                                        wire:target="applyCoupon"
                                        class="h-8 px-3.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] active:bg-[#E5D735] text-[#12181E] text-xs font-semibold transition shadow-none cursor-pointer shrink-0 disabled:opacity-50 flex items-center gap-1.5"
                                    >
                                        <span wire:loading.remove wire:target="applyCoupon">{{ __('Apply') }}</span>
                                        <span wire:loading wire:target="applyCoupon"><i class="fa-solid fa-spinner fa-spin text-xs"></i></span>
                                    </button>
                                </div>
                            @else
                                <div class="flex items-center justify-between p-2.5 rounded-[6px] bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/60 text-xs">
                                    <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-medium">
                                        <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400"></i>
                                        <span>{{ $appliedCouponCode }} (-Rp {{ number_format($discountAmount, 0, ',', '.') }})</span>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="removeCoupon"
                                        class="text-xs text-rose-600 dark:text-rose-400 hover:underline font-medium cursor-pointer"
                                    >
                                        {{ __('Remove') }}
                                    </button>
                                </div>
                            @endif

                            @if ($couponMessage && ! $appliedCouponCode)
                                <p class="text-[11px] font-medium flex items-center gap-1 {{ $couponValid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    <i class="fa-solid {{ $couponValid ? 'fa-circle-check' : 'fa-circle-exclamation' }} text-[10px]"></i>
                                    <span>{{ $couponMessage }}</span>
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="pt-3 border-t border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-between">
                        <div>
                            <span class="font-semibold text-sm text-[#12181E] dark:text-white block">{{ __('Total Amount Due') }}</span>
                            <span class="text-[11px] text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Includes all tax & fees') }}</span>
                        </div>
                        <span class="text-xl font-semibold font-mono text-[#12181E] dark:text-white">
                            Rp {{ number_format((float) $payment->net_amount_paid, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                <div class="p-4 rounded-[8px] bg-[#F8F9FA] dark:bg-[#141821] border border-[#E4E5E9] dark:border-[#1E2433] space-y-2 text-xs">
                    <span class="font-semibold text-[#12181E] dark:text-white block">{{ __('Included in :plan:', ['plan' => $payment->plan->name]) }}</span>
                    <ul class="space-y-1 text-[#5A6578] dark:text-[#9DA4B2]">
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-[10px]"></i>
                            <span>{{ $payment->plan->listingLimitLabel() }}</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-[10px]"></i>
                            <span>{{ $payment->plan->teamSeatLabel() }}</span>
                        </li>
                        @if ($payment->plan->hasFeature('custom_domain'))
                            <li class="flex items-center gap-2">
                                <i class="fa-solid fa-check text-emerald-600 dark:text-emerald-400 text-[10px]"></i>
                                <span>{{ __('Your own website address') }}</span>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
