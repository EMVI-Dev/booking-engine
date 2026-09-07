<div class="space-y-4">
    <x-filter-tabs padded>
        <x-filter-tab :href="route('brand.edit')" icon="fa-paintbrush" :active="request()->routeIs('brand.edit')">
            {{ __('Brand & Identity') }}
        </x-filter-tab>
        <x-filter-tab :href="route('storefront-settings.edit')" icon="fa-store" :active="request()->routeIs('storefront-settings.edit')">
            {{ __('Storefront & Policies') }}
        </x-filter-tab>
    </x-filter-tabs>
</div>
