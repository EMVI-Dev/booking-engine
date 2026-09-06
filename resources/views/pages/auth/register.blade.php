<x-layouts::auth :title="__('Create your account')">
    @php
        $platformDomain = app(\App\Services\DomainResolverService::class)->getPlatformDomain();
        $startStep = $errors->hasAny(['name', 'email', 'password', 'password_confirmation'])
            ? 1
            : ($errors->any() ? 2 : 1);
    @endphp

    <div
        class="flex flex-col gap-6"
        x-data="{
            step: {{ $startStep }},
            agencyName: @js(old('agency_name', '')),
            slug: @js(old('slug', '')),
            autoSlug: {{ old('slug') ? 'false' : 'true' }},
            agreedTerms: {{ old('terms') ? 'true' : 'false' }},
            submitting: false,
            platformDomain: @js($platformDomain),
            updateSlug() {
                if (this.autoSlug && this.agencyName) {
                    this.slug = this.agencyName.toLowerCase()
                        .replace(/[^\w\s-]/g, '')
                        .trim()
                        .replace(/\s+/g, '-');
                }
            },
            fieldValid(id) {
                const el = document.getElementById(id);
                return ! el || el.checkValidity();
            },
            accountReady() {
                return ['name', 'email', 'password', 'password_confirmation'].every((id) => this.fieldValid(id));
            },
            goToStep(next) {
                if (next > this.step && ! this.accountReady()) {
                    ['name', 'email', 'password', 'password_confirmation'].some((id) => {
                        const el = document.getElementById(id);
                        if (el && ! el.checkValidity()) {
                            el.reportValidity();
                            return true;
                        }
                        return false;
                    });
                    return;
                }
                this.step = next;
            },
            continueToBusiness() {
                this.goToStep(2);
            },
            handleSubmit(event) {
                if (! this.accountReady()) {
                    event.preventDefault();
                    this.step = 1;
                    this.$nextTick(() => {
                        ['name', 'email', 'password', 'password_confirmation'].some((id) => {
                            const el = document.getElementById(id);
                            if (el && ! el.checkValidity()) {
                                el.reportValidity();
                                return true;
                            }
                            return false;
                        });
                    });
                    return;
                }

                const business = document.getElementById('agency_name');
                if (business && ! business.checkValidity()) {
                    event.preventDefault();
                    this.step = 2;
                    this.$nextTick(() => business.reportValidity());
                    return;
                }

                if (! this.agreedTerms) {
                    event.preventDefault();
                    this.step = 2;
                    return;
                }

                this.submitting = true;
            },
        }"
    >
        <div class="space-y-2 text-center">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#FFEF4D]/40 bg-[#FFEF4D]/15 px-3 py-1 text-xs font-semibold text-[#8a7808] dark:border-[#FFEF4D]/30 dark:bg-[#FFEF4D]/10 dark:text-[#FFEF4D]">
                <i class="fa-solid fa-store text-xs"></i>
                {{ __('New operator') }}
            </span>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                {{ __('Start taking bookings') }}
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ __('A few details now. Bank account and bio can wait until after you sign in.') }}
            </p>
        </div>

        <div class="relative flex items-center justify-between px-2 pt-2">
            <div class="absolute top-6 right-6 left-6 -z-0 h-0.5 -translate-y-1/2 bg-slate-200 dark:bg-zinc-800"></div>
            <div
                class="absolute top-6 left-6 -z-0 h-0.5 -translate-y-1/2 bg-brand-400 motion-safe:transition-all motion-safe:duration-300"
                :style="'width: ' + ((step - 1) * 100) + '%; max-width: calc(100% - 48px);'"
            ></div>

            <button
                type="button"
                @click="goToStep(1)"
                class="relative z-10 flex cursor-pointer flex-col items-center gap-1.5 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-400/70"
                :aria-current="step === 1 ? 'step' : false"
            >
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold motion-safe:transition-colors motion-safe:duration-200"
                    :class="step === 1
                        ? 'bg-brand-400 text-brand-foreground shadow-sm ring-4 ring-brand-400/20'
                        : 'bg-[#12181E] text-[#FFEF4D]'"
                >
                    1
                </div>
                <span
                    class="text-xs font-medium"
                    :class="step === 1 ? 'font-semibold text-[#8a7808] dark:text-[#FFEF4D]' : 'text-slate-500'"
                >
                    {{ __('You') }}
                </span>
            </button>

            <button
                type="button"
                @click="goToStep(2)"
                class="relative z-10 flex cursor-pointer flex-col items-center gap-1.5 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-400/70"
                :aria-current="step === 2 ? 'step' : false"
            >
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold motion-safe:transition-colors motion-safe:duration-200"
                    :class="step === 2
                        ? 'bg-brand-400 text-brand-foreground shadow-sm ring-4 ring-brand-400/20'
                        : (step > 2 ? 'bg-[#12181E] text-[#FFEF4D]' : 'bg-slate-200 text-slate-500 dark:bg-zinc-800')"
                >
                    2
                </div>
                <span
                    class="text-xs font-medium"
                    :class="step === 2 ? 'font-semibold text-[#8a7808] dark:text-[#FFEF4D]' : 'text-slate-500'"
                >
                    {{ __('Your business') }}
                </span>
            </button>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form
            method="POST"
            action="{{ route('register.store') }}"
            class="mt-2 flex flex-col gap-5"
            @submit="handleSubmit($event)"
        >
            @csrf

            <div
                x-show="step === 1"
                class="space-y-4 motion-safe:transition-opacity motion-safe:duration-200"
                x-transition:enter="motion-safe:transition motion-safe:ease-out motion-safe:duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
            >
                <div>
                    <x-label for="name" :value="__('Your name')" required />
                    <x-input
                        id="name"
                        name="name"
                        :value="old('name')"
                        type="text"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="{{ __('Your name') }}"
                        :error="$errors->has('name')"
                    />
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-label for="email" :value="__('Work email')" required />
                    <x-input
                        id="email"
                        name="email"
                        :value="old('email')"
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="{{ __('you@email.com') }}"
                        :error="$errors->has('email')"
                    />
                    <x-input-error :messages="$errors->get('email')" />
                </div>

                <div>
                    <x-label for="password" :value="__('Password')" required />
                    <x-input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                        :error="$errors->has('password')"
                    />
                    <p class="mt-1.5 text-xs text-slate-600 dark:text-slate-400">
                        {{ __('At least 8 characters.') }}
                    </p>
                    <x-input-error :messages="$errors->get('password')" />
                </div>

                <div>
                    <x-label for="password_confirmation" :value="__('Type the password again')" required />
                    <x-input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                        :error="$errors->has('password_confirmation')"
                    />
                    <x-input-error :messages="$errors->get('password_confirmation')" />
                </div>

                <div class="pt-2">
                    <x-button type="button" variant="primary" class="w-full" @click="continueToBusiness()">
                        {{ __('Continue') }}
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </x-button>
                </div>
            </div>

            <div
                x-show="step === 2"
                x-cloak
                style="display: none;"
                class="space-y-4 motion-safe:transition-opacity motion-safe:duration-200"
                x-transition:enter="motion-safe:transition motion-safe:ease-out motion-safe:duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
            >
                <div>
                    <x-label for="agency_name" :value="__('Your business name')" required />
                    <x-input
                        id="agency_name"
                        name="agency_name"
                        x-model="agencyName"
                        @input="updateSlug()"
                        type="text"
                        required
                        autocomplete="organization"
                        placeholder="{{ __('Your business name') }}"
                        :error="$errors->has('agency_name')"
                    />
                    <x-input-error :messages="$errors->get('agency_name')" />
                </div>

                <div>
                    <x-label for="slug" :value="__('Your page address')" />
                    <x-input
                        id="slug"
                        name="slug"
                        x-model="slug"
                        @input="autoSlug = false"
                        type="text"
                        autocomplete="off"
                        placeholder="{{ __('your-page') }}"
                        :error="$errors->has('slug')"
                    />
                    <p class="mt-1.5 flex flex-wrap items-center gap-1 text-xs text-slate-600 dark:text-slate-400">
                        <span>{{ __('Guests will open:') }}</span>
                        <span
                            class="font-mono font-medium text-[#8a7808] dark:text-[#FFEF4D]"
                            x-text="(slug || 'your-name') + '.' + platformDomain"
                        ></span>
                    </p>
                    <x-input-error :messages="$errors->get('slug')" />
                </div>

                <div>
                    <x-label for="contact_whatsapp" :value="__('WhatsApp number')" />
                    <x-input
                        id="contact_whatsapp"
                        name="contact_whatsapp"
                        :value="old('contact_whatsapp')"
                        type="tel"
                        autocomplete="tel"
                        placeholder="{{ __('Your WhatsApp number') }}"
                        :error="$errors->has('contact_whatsapp')"
                    />
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">
                        {{ __('Optional. Guests can message you from your page.') }}
                    </p>
                    <x-input-error :messages="$errors->get('contact_whatsapp')" />
                </div>

                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/40">
                    <p class="text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ __('You can add your payout bank account later. We hold guest money until the trip, then send the listed price to you.') }}
                    </p>

                    <div class="border-t border-slate-200 pt-2 dark:border-zinc-700/60">
                        <x-checkbox id="terms" name="terms" value="1" x-model="agreedTerms" required>
                            <span class="select-none text-xs font-semibold text-slate-800 dark:text-slate-200">
                                {{ __('I agree to the') }}
                                <a href="{{ route('legal.terms') }}" target="_blank" class="underline">{{ __('platform terms') }}</a>
                                {{ __('and') }}
                                <a href="{{ route('legal.privacy') }}" target="_blank" class="underline">{{ __('privacy') }}</a>
                                <span class="text-rose-500">*</span>
                            </span>
                        </x-checkbox>
                        <x-input-error :messages="$errors->get('terms')" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-button type="button" variant="outline" class="w-1/3" @click="goToStep(1)">
                        {{ __('Back') }}
                    </x-button>
                    <x-button
                        type="submit"
                        variant="primary"
                        class="w-2/3 font-semibold shadow-sm"
                        x-bind:disabled="!agreedTerms || submitting"
                        x-bind:class="(!agreedTerms || submitting) ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'cursor-pointer'"
                        data-test="register-user-button"
                    >
                        <span x-show="!submitting" class="inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-check text-xs"></i>
                            {{ __('Create account') }}
                        </span>
                        <span x-show="submitting" x-cloak class="inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-notch animate-spin text-xs"></i>
                            {{ __('Creating your account…') }}
                        </span>
                    </x-button>
                </div>
            </div>
        </form>

        <div class="pt-1 text-center text-sm text-slate-600 dark:text-slate-400">
            <span>{{ __('Already have an account?') }}</span>
            <a
                href="{{ route('login') }}"
                class="font-semibold text-[#8a7808] underline hover:text-[#6b5d06] dark:text-[#FFEF4D] dark:hover:text-[#fae639]"
                wire:navigate
            >{{ __('Log in') }}</a>
        </div>
    </div>
</x-layouts::auth>
