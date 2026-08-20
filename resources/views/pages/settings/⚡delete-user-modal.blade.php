<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable maxWidth="lg">
    <form method="POST" wire:submit="deleteUser" class="p-6 space-y-5">
        <div>
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                {{ __('Are you sure you want to delete your account?') }}
            </h3>

            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>
        </div>

        <div>
            <x-label for="password" :value="__('Password')" required />
            <x-input
                id="password"
                wire:model="password"
                type="password"
                :placeholder="__('Password')"
                :error="$errors->has('password')"
            />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <x-button variant="outline" type="button" x-on:click="$dispatch('close')">
                {{ __('Cancel') }}
            </x-button>

            <x-button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </x-button>
        </div>
    </form>
</x-modal>
