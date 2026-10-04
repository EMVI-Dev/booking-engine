@props(['tab' => null])

@php
    $currentTab = $tab ?? request()->query('tab', 'gallery');
    $isWebsite = request()->routeIs('website-settings.edit', 'gallery-settings.edit', 'faq-settings.edit', 'contact-settings.edit');
@endphp

<div class="space-y-4">
    <x-filter-tabs padded>
        <x-filter-tab :href="route('brand.edit')" icon="fa-paintbrush" :active="request()->routeIs('brand.edit')">
            {{ __('Brand & Identity') }}
        </x-filter-tab>
        <x-filter-tab :href="route('storefront-settings.edit')" icon="fa-store" :active="request()->routeIs('storefront-settings.edit')">
            {{ __('Storefront & Policies') }}
        </x-filter-tab>
        <x-filter-tab :href="route('review-settings.edit')" icon="fa-star" :active="request()->routeIs('review-settings.edit')">
            {{ __('Reviews') }}
        </x-filter-tab>
        <x-filter-tab :href="route('website-settings.edit', ['tab' => 'gallery'])" icon="fa-images" :active="$isWebsite && $currentTab === 'gallery'">
            {{ __('Gallery') }}
        </x-filter-tab>
        <x-filter-tab :href="route('website-settings.edit', ['tab' => 'faq'])" icon="fa-circle-question" :active="$isWebsite && $currentTab === 'faq'">
            {{ __('FAQ') }}
        </x-filter-tab>
        <x-filter-tab :href="route('website-settings.edit', ['tab' => 'contact'])" icon="fa-envelope-open-text" :active="$isWebsite && $currentTab === 'contact'">
            {{ __('Contact') }}
        </x-filter-tab>
    </x-filter-tabs>
</div>

