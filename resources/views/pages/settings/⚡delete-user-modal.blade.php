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
    <form method="POST" wire:submit="deleteUser" class="p-5 sm:p-6 space-y-5 rounded-t-[16px] sm:rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433]">
        <div class="mx-auto -mt-2 mb-2 h-1 w-10 shrink-0 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

        <div class="space-y-1.5">
            <h3 class="text-sm sm:text-base font-semibold text-[#12181E] dark:text-white">
                {{ __('Are you sure you want to delete your account?') }}
            </h3>

            <p class="text-xs text-[#5A6578] dark:text-[#9DA4B2] leading-relaxed">
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

        <div class="flex items-center justify-end gap-2.5 pt-2">
            <x-button variant="outline" type="button" class="rounded-[6px]" x-on:click="$dispatch('close')">
                {{ __('Cancel') }}
            </x-button>

            <x-button variant="danger" type="submit" class="rounded-[6px]" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </x-button>
        </div>
    </form>
</x-modal>
