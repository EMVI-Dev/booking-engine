<x-layouts::auth :title="__('Operator Log in')">
    <div class="flex flex-col gap-6" x-data="{
        fillCredentials(email, password) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
        }
    }">
        <div class="text-center space-y-2">
            <span
                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider bg-white/[0.06] border border-white/[0.12] text-[#FFEF4D]">
                <i class="fa-solid fa-compass text-[10px]"></i>
                {{ __('OPERATOR PORTAL') }}
            </span>
            <h1 class="text-2xl font-black tracking-tight text-white">
                {{ __('Welcome back') }}
            </h1>
            <p class="text-xs text-zinc-400 max-w-sm mx-auto">
                {{ __('Sign in to manage your tour packages, reservations, calendar, and payouts.') }}
            </p>
        </div>

        @php
            $hostOperator = request()->attributes->get('current_operator');
            $isDemoHost = $hostOperator instanceof \App\Models\Operator && $hostOperator->isDemo();
            $demoLoginPassword = filled(config('demo.password'))
                ? (string) config('demo.password')
                : (app()->environment('local', 'testing', 'staging') ? 'password' : '');
            $showDemoFill = $isDemoHost && filled($demoLoginPassword);
        @endphp
        @if ($showDemoFill)
            <div class="p-3.5 rounded-xl bg-white/[0.02] border border-white/[0.08] text-xs space-y-2">
                <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-zinc-400 block">{{ __('Click to fill a lookaround account') }}</span>
                <button type="button" @click="fillCredentials('{{ config('demo.email', 'demo@travelengine.id') }}', @js($demoLoginPassword))"
                    class="w-full p-2.5 rounded-lg bg-black/40 border border-white/[0.08] hover:border-[#FFEF4D]/50 text-left transition cursor-pointer group">
                    <span class="font-bold text-white block text-xs group-hover:text-[#FFEF4D]">{{ __('Demo operator') }}</span>
                    <span class="text-[10px] text-zinc-400 font-mono">{{ config('demo.email', 'demo@travelengine.id') }}</span>
                </button>
            </div>
        @endif

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ url('/login') }}" class="flex flex-col gap-5">
            @csrf

            <!-- Email Address -->
            <div>
                <x-label for="email" :value="__('Email address')" required class="text-zinc-300 font-mono text-xs" />
                <x-input id="email" name="email" :value="old('email')" type="email" required autofocus
                    autocomplete="email" placeholder="{{ __('you@email.com') }}" :error="$errors->has('email')" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <!-- Password -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <x-label for="password" :value="__('Password')" required class="text-zinc-300 font-mono text-xs" />
                    @if (Route::has('password.request'))
                        <a class="text-xs font-mono text-zinc-400 hover:text-[#FFEF4D] transition"
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
            <div class="flex items-center justify-between text-xs text-zinc-400">
                <x-checkbox name="remember" :label="__('Remember this device')" :checked="old('remember')" />
            </div>

            <div>
                <x-button variant="primary" type="submit" class="w-full h-11 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-xs transition shadow-sm cursor-pointer"
                    data-test="login-button">
                    {{ __('Sign In to Operator Portal') }}
                </x-button>
            </div>
        </form>

        <div class="text-xs text-center text-zinc-400 font-mono pt-1">
            @if (\App\Models\PlatformSetting::current()->operatorRegistrationAllowed())
                <span>{{ __('New tour operator or guide?') }}</span>
                <a href="{{ route('register') }}"
                    class="font-bold text-[#FFEF4D] hover:underline"
                    wire:navigate>{{ __('Create an account') }}</a>
            @else
                <span>{{ __('We are preparing operator sign-up. Coming soon.') }}</span>
            @endif
        </div>
    </div>
</x-layouts::auth>
