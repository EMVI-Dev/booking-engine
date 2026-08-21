<x-layouts::auth :title="__('Create Tour Operator Storefront')">
    <div class="flex flex-col gap-6" x-data="{
        step: 1,
        agencyName: @js(old('agency_name', '')),
        slug: @js(old('slug', '')),
        autoSlug: true,
        agreedTerms: false,
        updateSlug() {
            if (this.autoSlug && this.agencyName) {
                this.slug = this.agencyName.toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .trim()
                    .replace(/\s+/g, '-');
            }
        },
        goToStep(s) {
            this.step = s;
        }
    }">
        <!-- Header -->
        <div class="text-center space-y-2">
            <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                <i class="fa-solid fa-store text-xs"></i>
                {{ __('Tour Operator Onboarding') }}
            </span>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Launch your booking storefront') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Set up your direct booking page with real-time inventory in 3 quick steps.') }}
            </p>
        </div>

        <!-- Step Indicator -->
        <div class="flex items-center justify-between relative px-2 pt-2">
            <div class="absolute left-6 right-6 top-6 -translate-y-1/2 h-0.5 bg-slate-200 dark:bg-zinc-800 -z-0"></div>
            <div class="absolute left-6 top-6 -translate-y-1/2 h-0.5 bg-indigo-600 transition-all duration-300 -z-0"
                :style="'width: ' + ((step - 1) / 2 * 100) + '%; max-width: calc(100% - 48px);'"></div>

            <!-- Step 1 Indicator -->
            <button type="button" @click="goToStep(1)"
                class="relative z-10 flex flex-col items-center gap-1.5 focus:outline-none cursor-pointer">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-200"
                    :class="step >= 1 ? 'bg-indigo-600 text-white shadow-sm ring-4 ring-indigo-50 dark:ring-indigo-950/80' :
                        'bg-slate-200 dark:bg-zinc-800 text-slate-500'">
                    1
                </div>
                <span class="text-xs font-medium"
                    :class="step === 1 ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-slate-500'">
                    {{ __('Account') }}
                </span>
            </button>

            <!-- Step 2 Indicator -->
            <button type="button" @click="goToStep(2)"
                class="relative z-10 flex flex-col items-center gap-1.5 focus:outline-none cursor-pointer">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-200"
                    :class="step >= 2 ? 'bg-indigo-600 text-white shadow-sm ring-4 ring-indigo-50 dark:ring-indigo-950/80' :
                        'bg-slate-200 dark:bg-zinc-800 text-slate-500'">
                    2
                </div>
                <span class="text-xs font-medium"
                    :class="step === 2 ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-slate-500'">
                    {{ __('Storefront') }}
                </span>
            </button>

            <!-- Step 3 Indicator -->
            <button type="button" @click="goToStep(3)"
                class="relative z-10 flex flex-col items-center gap-1.5 focus:outline-none cursor-pointer">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-200"
                    :class="step >= 3 ? 'bg-indigo-600 text-white shadow-sm ring-4 ring-indigo-50 dark:ring-indigo-950/80' :
                        'bg-slate-200 dark:bg-zinc-800 text-slate-500'">
                    3
                </div>
                <span class="text-xs font-medium"
                    :class="step === 3 ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-slate-500'">
                    {{ __('Payouts') }}
                </span>
            </button>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5 mt-2">
            @csrf

            <!-- STEP 1: Account Credentials -->
            <div x-show="step === 1" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0"
                class="space-y-4">
                <div>
                    <x-label for="name" :value="__('Your Full Name (Owner)')" required />
                    <x-input id="name" name="name" :value="old('name')" type="text" required autofocus
                        autocomplete="name" placeholder="John Doe" :error="$errors->has('name')" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-label for="email" :value="__('Work Email Address')" required />
                    <x-input id="email" name="email" :value="old('email')" type="email" required
                        autocomplete="email" placeholder="john@example.com" :error="$errors->has('email')" />
                    <x-input-error :messages="$errors->get('email')" />
                </div>

                <div>
                    <x-label for="password" :value="__('Password')" required />
                    <x-input id="password" name="password" type="password" required autocomplete="new-password"
                        placeholder="••••••••" :error="$errors->has('password')" />
                    <x-input-error :messages="$errors->get('password')" />
                </div>

                <div>
                    <x-label for="password_confirmation" :value="__('Confirm Password')" required />
                    <x-input id="password_confirmation" name="password_confirmation" type="password" required
                        autocomplete="new-password" placeholder="••••••••" :error="$errors->has('password_confirmation')" />
                    <x-input-error :messages="$errors->get('password_confirmation')" />
                </div>

                <div class="pt-2">
                    <x-button type="button" variant="primary" class="w-full" @click="step = 2">
                        {{ __('Continue to Storefront Details') }}
                        <i class="fa-solid fa-arrow-right ml-1 text-xs"></i>
                    </x-button>
                </div>
            </div>

            <!-- STEP 2: Storefront & Brand -->
            <div x-show="step === 2" x-cloak style="display: none;"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4"
                x-transition:enter-end="opacity-100 translate-x-0" class="space-y-4">
                <div>
                    <x-label for="agency_name" :value="__('Agency or Guide Name')" required />
                    <x-input id="agency_name" name="agency_name" x-model="agencyName" @input="updateSlug()"
                        type="text" placeholder="e.g. Bali Snorkel & Trek Tours" :error="$errors->has('agency_name')" />
                    <x-input-error :messages="$errors->get('agency_name')" />
                </div>

                <div>
                    <x-label for="slug" :value="__('Storefront Subdomain')" required />
                    <x-input id="slug" name="slug" x-model="slug" @input="autoSlug = false" type="text"
                        placeholder="balitours" :error="$errors->has('slug')" />
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1">
                        <span>{{ __('Your direct URL will be:') }}</span>
                        <span class="font-mono font-medium text-indigo-600 dark:text-indigo-400"
                            x-text="(slug || 'your-agency') + '.booking.emvi'"></span>
                    </p>
                    <x-input-error :messages="$errors->get('slug')" />
                </div>

                <div>
                    <x-label for="contact_whatsapp" :value="__('WhatsApp Contact Number')" />
                    <x-input id="contact_whatsapp" name="contact_whatsapp" :value="old('contact_whatsapp')" type="text"
                        placeholder="+62 812 3456 7890" :error="$errors->has('contact_whatsapp')" />
                    <p class="text-xs text-slate-500 mt-1">
                        {{ __('Used as instant guest contact fallback on your storefront.') }}</p>
                    <x-input-error :messages="$errors->get('contact_whatsapp')" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="button" variant="outline" class="w-1/3" @click="step = 1">
                        {{ __('Back') }}
                    </x-button>
                    <x-button type="button" variant="primary" class="w-2/3" @click="step = 3">
                        {{ __('Continue to Payouts') }}
                        <i class="fa-solid fa-arrow-right ml-1 text-xs"></i>
                    </x-button>
                </div>
            </div>

            <!-- STEP 3: Payouts & Launch -->
            <div x-show="step === 3" x-cloak style="display: none;"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0"
                class="space-y-4">
                <div>
                    <x-label for="bank_account_ref" :value="__('Bank Account Reference / Settlement Ref')" />
                    <x-input id="bank_account_ref" name="bank_account_ref" :value="old('bank_account_ref')" type="text"
                        placeholder="e.g. BCA - 1234567890" :error="$errors->has('bank_account_ref')" />
                    <p class="text-xs text-slate-500 mt-1">
                        {{ __('Used for direct bank payouts and automated split settlements. Can be updated or customized later in dashboard.') }}</p>
                    <x-input-error :messages="$errors->get('bank_account_ref')" />
                </div>

                <div>
                    <x-label for="bio" :value="__('Short Agency Bio / Introduction')" />
                    <x-textarea id="bio" name="bio" rows="2"
                        placeholder="Tell guests about your experience, local expertise, and tour offerings..."
                        :error="$errors->has('bio')">{{ old('bio') }}</x-textarea>
                    <x-input-error :messages="$errors->get('bio')" />
                </div>

                <!-- Terms & Protection Agreement Checkbox -->
                <div
                    class="rounded-2xl p-4 bg-slate-50 dark:bg-zinc-800/40 border border-slate-200 dark:border-zinc-800 space-y-3">
                    <div class="space-y-1 text-xs text-slate-600 dark:text-slate-400">
                        <p class="font-bold text-slate-900 dark:text-slate-200 flex items-center gap-1.5">
                            <i class="fa-solid fa-shield-halved text-indigo-500"></i>
                            {{ __('Storefront Terms & Protection') }}
                        </p>
                        <p>{{ __('By creating your storefront, you agree to our tour operator platform terms, automated split settlements, and customer data protection compliance (UU PDP).') }}
                        </p>
                    </div>

                    <div class="pt-2 border-t border-slate-200 dark:border-zinc-700/60">
                        <x-checkbox
                            id="terms"
                            name="terms"
                            value="1"
                            x-model="agreedTerms"
                            required
                        >
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 select-none">
                                {{ __('I agree to the Storefront Terms, Settlement Schedule & Privacy Policy') }} <span class="text-rose-500">*</span>
                            </span>
                        </x-checkbox>
                        <x-input-error :messages="$errors->get('terms')" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="button" variant="outline" class="w-1/3" @click="step = 2">
                        {{ __('Back') }}
                    </x-button>
                    <x-button
                        type="submit"
                        variant="primary"
                        class="w-2/3 shadow-sm font-semibold transition-all duration-200"
                        x-bind:disabled="!agreedTerms"
                        x-bind:class="!agreedTerms ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'cursor-pointer'"
                        data-test="register-user-button"
                    >
                        <i class="fa-solid fa-circle-check mr-1.5 text-emerald-400"></i>
                        {{ __('Launch Storefront') }}
                    </x-button>
                </div>
            </div>
        </form>

        <div class="text-sm text-center text-slate-600 dark:text-slate-400 pt-1">
            <span>{{ __('Already have an operator account?') }}</span>
            <a href="{{ route('login') }}"
                class="font-semibold underline text-indigo-600 dark:text-indigo-400 hover:text-indigo-500"
                wire:navigate>{{ __('Log in') }}</a>
        </div>
    </div>
</x-layouts::auth>
