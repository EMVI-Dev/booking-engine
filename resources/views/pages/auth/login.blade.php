<x-layouts::auth :title="__('Operator Log in')">
    <div class="flex flex-col gap-6" x-data="{
        fillCredentials(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
        }
    }">
        <div class="text-center space-y-2">
            <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#FFEF4D]/15 text-[#8a7808] dark:bg-[#FFEF4D]/10 dark:text-[#FFEF4D] border border-[#FFEF4D]/40 dark:border-[#FFEF4D]/30">
                <i class="fa-solid fa-compass text-xs"></i>
                {{ __('Operator Portal') }}
            </span>
            <h1 class="text-2xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
                {{ __('Welcome back') }}
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Sign in to manage your tour packages, reservations, calendar, and payouts.') }}
            </p>
        </div>

        <!-- Demo Accounts Quick Fill (Local / Development Helper) -->
        @if (app()->environment('local', 'testing', 'staging'))
            <div
                class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200/80 dark:border-zinc-700 text-xs space-y-2">
                <span
                    class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">{{ __('Demo Test Accounts (Click to Fill)') }}</span>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="fillCredentials('baliridetours@gmail.com', 'password')"
                        class="p-2 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 hover:border-indigo-500 hover:text-indigo-600 text-left transition cursor-pointer group shadow-2xs">
                        <span
                            class="font-bold text-slate-800 dark:text-zinc-200 block text-[11px] group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Operator</span>
                        <span
                            class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono">baliridetours@gmail.com</span>
                    </button>
                    <button type="button" @click="fillCredentials('admin@emvi.dev', 'password')"
                        class="p-2 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 hover:border-indigo-500 hover:text-indigo-600 text-left transition cursor-pointer group shadow-2xs">
                        <span
                            class="font-bold text-slate-800 dark:text-zinc-200 block text-[11px] group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Platform
                            Admin</span>
                        <span class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono">admin@emvi.dev</span>
                    </button>
                </div>
            </div>
        @endif

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <!-- Email Address -->
            <div>
                <x-label for="email" :value="__('Email address')" required />
                <x-input id="email" name="email" :value="old('email')" type="email" required autofocus
                    autocomplete="email" placeholder="{{ __('you@email.com') }}" :error="$errors->has('email')" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <!-- Password -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <x-label for="password" :value="__('Password')" required />
                    @if (Route::has('password.request'))
                        <a class="text-xs font-medium text-[#8a7808] hover:underline dark:text-[#FFEF4D]"
                            href="{{ route('password.request') }}" wire:navigate>
                            {{ __('Forgot password?') }}
                        </a>
                    @endif
                </div>
                <x-input id="password" name="password" type="password" required autocomplete="current-password"
                    placeholder="••••••••" :error="$errors->has('password')" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <!-- Remember Me -->
            <div class="flex items-center justify-between">
                <x-checkbox name="remember" :label="__('Remember this device')" :checked="old('remember')" />
            </div>

            <div>
                <x-button variant="primary" type="submit" class="w-full shadow-sm font-semibold"
                    data-test="login-button">
                    {{ __('Sign In to Operator Portal') }}
                </x-button>
            </div>
        </form>

        <div class="text-sm text-center text-zinc-600 dark:text-zinc-400">
            <span>{{ __('New tour operator or guide?') }}</span>
            <a href="{{ route('register') }}"
                class="font-semibold text-[#8a7808] underline hover:text-[#6b5d06] dark:text-[#FFEF4D] dark:hover:text-[#fae639]"
                wire:navigate>{{ __('Create an account') }}</a>
        </div>
    </div>
</x-layouts::auth>
