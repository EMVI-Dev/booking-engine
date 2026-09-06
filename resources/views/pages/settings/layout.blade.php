<div class="w-full space-y-6">
    <x-filter-tabs padded>
        <x-filter-tab :href="route('profile.edit')" icon="fa-user" :active="request()->routeIs('profile.edit')">
            {{ __('Profile') }}
        </x-filter-tab>
        <x-filter-tab :href="route('security.edit')" icon="fa-shield-halved" :active="request()->routeIs('security.edit')">
            {{ __('Security & 2FA') }}
        </x-filter-tab>
        <x-filter-tab :href="route('appearance.edit')" icon="fa-circle-half-stroke" :active="request()->routeIs('appearance.edit')">
            {{ __('Appearance') }}
        </x-filter-tab>
    </x-filter-tabs>

    <div class="w-full">
        @if (isset($heading))
            <div class="mb-4">
                <h2 class="text-xl font-bold text-op-ink">{{ $heading }}</h2>
                @if (isset($subheading))
                    <p class="mt-0.5 text-xs text-op-subtle sm:text-sm">{{ $subheading }}</p>
                @endif
            </div>
        @endif

        <div class="w-full">
            {{ $slot }}
        </div>
    </div>
</div>
