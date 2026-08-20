<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Profile information')" :subheading="__('Update your account\'s profile information and email address.')">
        <div class="space-y-6">
            <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
                <form wire:submit="updateProfileInformation" class="space-y-4">
                    <div>
                        <x-label for="name" :value="__('Name')" required />
                        <x-input
                            id="name"
                            wire:model="name"
                            type="text"
                            required
                            autofocus
                            autocomplete="name"
                            :error="$errors->has('name')"
                        />
                        <x-input-error :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-label for="email" :value="__('Email address')" required />
                        <x-input
                            id="email"
                            wire:model="email"
                            type="email"
                            required
                            autocomplete="email"
                            :error="$errors->has('email')"
                        />
                        <x-input-error :messages="$errors->get('email')" />

                        @if ($this->hasUnverifiedEmail)
                            <div class="mt-2 text-xs text-slate-600 dark:text-slate-400">
                                {{ __('Your email address is unverified.') }}

                                <button
                                    type="button"
                                    class="text-indigo-600 dark:text-indigo-400 font-semibold underline hover:text-indigo-700 cursor-pointer ml-1"
                                    wire:click.prevent="resendVerificationNotification"
                                >
                                    {{ __('Click here to re-send the verification email.') }}
                                </button>

                                @if (session('status') === 'verification-link-sent')
                                    <p class="mt-1 font-medium text-emerald-600 dark:text-emerald-400">
                                        {{ __('A new verification link has been sent to your email address.') }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-4 pt-2">
                        <x-button variant="primary" type="submit" data-test="update-profile-button" class="shadow-sm">
                            <i class="fa-solid fa-floppy-disk mr-1 text-xs"></i>
                            {{ __('Save') }}
                        </x-button>

                        <div x-data="{ shown: false, timeout: null }"
                             x-init="@this.on('profile-updated', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2000); })"
                             x-show.transition.out.opacity.duration.1500ms="shown"
                             x-transition:leave.opacity.duration.1500ms
                             style="display: none;"
                             class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-check"></i>
                            {{ __('Saved.') }}
                        </div>
                    </div>
                </form>
            </div>

            @if ($this->showDeleteUser)
                <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs">
                    <livewire:pages::settings.delete-user-form />
                </div>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
