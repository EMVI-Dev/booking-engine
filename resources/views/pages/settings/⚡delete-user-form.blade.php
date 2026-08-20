<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="space-y-4">
    <div>
        <h3 class="text-base font-semibold text-red-600 dark:text-red-400">{{ __('Delete account') }}</h3>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ __('Permanently delete your account and all associated data.') }}</p>
    </div>

    <x-button
        variant="danger"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        data-test="delete-user-button"
    >
        {{ __('Delete account') }}
    </x-button>

    <livewire:pages::settings.delete-user-modal />
</section>
