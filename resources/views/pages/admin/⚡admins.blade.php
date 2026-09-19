<?php

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Administrators')] #[Layout('layouts.admin')] class extends Component
{
    use WithPagination;

    public string $search = '';

    // Create Admin Modal State
    public bool $showCreateModal = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Revoke Admin Access State
    public bool $showRevokeModal = false;

    public ?string $targetUserId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function metrics(): array
    {
        $total = User::where('is_admin', true)->count();
        $twoFactor = User::where('is_admin', true)->whereNotNull('two_factor_confirmed_at')->count();
        $passkeys = User::where('is_admin', true)->has('passkeys')->count();

        return [
            'total' => $total,
            'two_factor' => $twoFactor,
            'passkeys' => $passkeys,
        ];
    }

    #[Computed]
    public function targetUser(): ?User
    {
        if (! $this->targetUserId) {
            return null;
        }

        return User::find($this->targetUserId);
    }

    #[Computed]
    public function administrators(): LengthAwarePaginator
    {
        return User::query()
            ->where('is_admin', true)
            ->withCount('passkeys')
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->latest('created_at')
            ->paginate(15);
    }

    public function openCreateModal(): void
    {
        $this->reset(['name', 'email', 'password', 'password_confirmation']);
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->reset(['name', 'email', 'password', 'password_confirmation']);
        $this->resetValidation();
    }

    public function createAdministrator(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        $this->closeCreateModal();
        $this->dispatch('toast', message: __('Platform administrator created successfully.'), type: 'success');
    }

    public function confirmRevokeAdmin(string $userId): void
    {
        if ($userId === (string) Auth::id()) {
            $this->dispatch('toast', message: __('You cannot revoke your own platform administrator privileges.'), type: 'error');

            return;
        }

        if (User::where('is_admin', true)->count() <= 1) {
            $this->dispatch('toast', message: __('Cannot revoke access: the platform must retain at least one administrator.'), type: 'error');

            return;
        }

        $this->targetUserId = $userId;
        $this->showRevokeModal = true;
    }

    public function closeRevokeModal(): void
    {
        $this->showRevokeModal = false;
        $this->targetUserId = null;
    }

    public function executeRevokeAdmin(): void
    {
        if ($this->targetUserId === (string) Auth::id()) {
            $this->closeRevokeModal();
            $this->dispatch('toast', message: __('You cannot revoke your own administrator privileges.'), type: 'error');

            return;
        }

        if (User::where('is_admin', true)->count() <= 1) {
            $this->closeRevokeModal();
            $this->dispatch('toast', message: __('The platform must have at least one active administrator.'), type: 'error');

            return;
        }

        $user = User::find($this->targetUserId);

        if ($user) {
            $user->update(['is_admin' => false]);
            $this->dispatch('toast', message: __(':name has been removed from platform administrators.', ['name' => $user->name]), type: 'success');
        }

        $this->closeRevokeModal();
    }
}; ?>

<div class="space-y-6">
    <x-page-header
        :title="__('Platform Administrators')"
        :subtitle="__('Manage platform staff and credentials with full administrative access.')"
        icon="fa-shield-halved"
    >
        <x-slot:actions>
            <x-button type="button" wire:click="openCreateModal" class="cursor-pointer shadow-xs">
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>{{ __('Add administrator') }}</span>
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Security Metrics Strip --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-metric-card
            :label="__('Total administrators')"
            :value="$this->metrics['total']"
            :hint="__('Staff with platform command access')"
            icon="fa-user-shield"
            tone="featured"
        />

        <x-metric-card
            :label="__('Two-factor enabled')"
            :value="$this->metrics['two_factor']"
            :suffix="'/ '.$this->metrics['total'].' '.__('admins')"
            :hint="__('Accounts protected by 2FA')"
            icon="fa-lock"
            :tone="$this->metrics['two_factor'] === $this->metrics['total'] ? 'success' : 'warning'"
        />

        <x-metric-card
            :label="__('Passkeys configured')"
            :value="$this->metrics['passkeys']"
            :hint="__('Accounts with biometric passkeys')"
            icon="fa-fingerprint"
        />
    </div>

    <x-toolbar>
        <div class="w-full sm:w-72">
            <x-search-input
                wire:model.live.debounce.300ms="search"
                :placeholder="__('Search by name or email...')"
            />
        </div>
    </x-toolbar>

    {{-- Administrators Table Card --}}
    <div class="rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs overflow-hidden">
        {{-- Mobile Responsive Card List (md:hidden) --}}
        <div class="md:hidden divide-y divide-slate-100 dark:divide-[#1e2433]">
            @forelse ($this->administrators as $admin)
                @php
                    $isCurrentUser = $admin->id === Auth::id();
                    $has2fa = filled($admin->two_factor_confirmed_at);
                @endphp
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-[#FFEF4D] text-[#090d16] font-black flex items-center justify-center text-xs shrink-0 shadow-xs">
                                {{ strtoupper(substr($admin->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-extrabold text-sm text-slate-900 dark:text-white truncate">
                                        {{ $admin->name }}
                                    </span>
                                    @if ($isCurrentUser)
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#FFEF4D]/20 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30">
                                            {{ __('You') }}
                                        </span>
                                    @endif
                                </div>
                                <span class="font-mono text-xs text-slate-500 dark:text-slate-400 block truncate">
                                    {{ $admin->email }}
                                </span>
                            </div>
                        </div>

                        @if (! $isCurrentUser && $this->metrics['total'] > 1)
                            <button
                                type="button"
                                wire:click="confirmRevokeAdmin('{{ $admin->id }}')"
                                class="p-2 rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer text-xs"
                                title="{{ __('Revoke admin access') }}"
                            >
                                <i class="fa-solid fa-user-minus"></i>
                            </button>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 flex-wrap text-xs pt-1">
                        @if ($has2fa)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-900/60">
                                <i class="fa-solid fa-shield-check text-[10px]"></i>
                                <span>{{ __('2FA Active') }}</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200/80 dark:border-amber-900/60">
                                <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                                <span>{{ __('2FA Disabled') }}</span>
                            </span>
                        @endif

                        @if ($admin->passkeys_count > 0)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 border border-blue-200/80 dark:border-blue-900/60">
                                <i class="fa-solid fa-fingerprint text-[10px]"></i>
                                <span>{{ trans_choice('{1} :count Passkey|[2,*] :count Passkeys', $admin->passkeys_count) }}</span>
                            </span>
                        @endif

                        <span class="text-[11px] text-slate-400 dark:text-slate-500 ml-auto font-mono">
                            {{ $admin->created_at?->format('M j, Y') }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center text-slate-400">
                    <i class="fa-solid fa-user-shield text-3xl mb-2 block opacity-40"></i>
                    <p class="font-bold text-sm text-slate-600 dark:text-slate-300">{{ __('No administrators found.') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Desktop Table (hidden md:table) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-[#1e2433] bg-slate-50/50 dark:bg-[#10141d] font-bold text-[11px] text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-6">{{ __('Administrator') }}</th>
                        <th class="py-3.5 px-4">{{ __('2FA Security') }}</th>
                        <th class="py-3.5 px-4">{{ __('Passkeys') }}</th>
                        <th class="py-3.5 px-4">{{ __('Added') }}</th>
                        <th class="py-3.5 px-6 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                    @forelse ($this->administrators as $admin)
                        @php
                            $isCurrentUser = $admin->id === Auth::id();
                            $has2fa = filled($admin->two_factor_confirmed_at);
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-[#121620]/60 transition">
                            <td class="py-3.5 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-[#FFEF4D] text-[#090d16] font-black flex items-center justify-center text-xs shrink-0 shadow-xs">
                                        {{ strtoupper(substr($admin->name, 0, 2)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-slate-900 dark:text-white truncate">
                                                {{ $admin->name }}
                                            </span>
                                            @if ($isCurrentUser)
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#FFEF4D]/20 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30">
                                                    {{ __('You') }}
                                                </span>
                                            @endif
                                        </div>
                                        <span class="font-mono text-slate-500 dark:text-slate-400 block truncate">
                                            {{ $admin->email }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($has2fa)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-900/60">
                                        <i class="fa-solid fa-shield-check text-[10px]"></i>
                                        <span>{{ __('Active') }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200/80 dark:border-amber-900/60">
                                        <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                                        <span>{{ __('Disabled') }}</span>
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($admin->passkeys_count > 0)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 border border-blue-200/80 dark:border-blue-900/60">
                                        <i class="fa-solid fa-fingerprint text-[10px]"></i>
                                        <span>{{ trans_choice('{1} :count passkey|[2,*] :count passkeys', $admin->passkeys_count) }}</span>
                                    </span>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500 text-[11px] font-medium">
                                        {{ __('None') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                                {{ $admin->created_at?->format('d M Y') }}
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                @if ($isCurrentUser)
                                    <span class="text-slate-400 dark:text-slate-500 text-[11px] italic font-medium">
                                        {{ __('Current session') }}
                                    </span>
                                @elseif ($this->metrics['total'] > 1)
                                    <button
                                        type="button"
                                        wire:click="confirmRevokeAdmin('{{ $admin->id }}')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-transparent hover:border-rose-200 dark:hover:border-rose-900/50 transition cursor-pointer"
                                    >
                                        <i class="fa-solid fa-user-minus text-[11px]"></i>
                                        <span>{{ __('Revoke') }}</span>
                                    </button>
                                @else
                                    <span class="text-slate-400 dark:text-slate-500 text-[11px] italic">
                                        {{ __('Sole admin') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400">
                                <i class="fa-solid fa-user-shield text-3xl mb-2 block opacity-40"></i>
                                <p class="font-bold text-sm text-slate-600 dark:text-slate-300">{{ __('No administrators found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->administrators->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-[#1e2433]">
                {{ $this->administrators->links() }}
            </div>
        @endif
    </div>

    {{-- Add Administrator Modal --}}
    <x-modal name="add-administrator-modal" :show="$showCreateModal" maxWidth="md">
        <form wire:submit="createAdministrator" class="p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                <div class="flex items-center gap-2.5">
                    <span class="p-1.5 rounded-xl bg-[#FFEF4D]/20 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30 text-xs">
                        <i class="fa-solid fa-user-plus"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            {{ __('Add platform administrator') }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Grants full access to operator management, plans, and platform config.') }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="closeCreateModal"
                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer"
                >
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <x-label for="name" :value="__('Full name')" required />
                    <x-input
                        id="name"
                        wire:model="name"
                        type="text"
                        required
                        placeholder="{{ __('e.g. Sarah Connor') }}"
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
                        placeholder="{{ __('e.g. sarah@platform.com') }}"
                        :error="$errors->has('email')"
                    />
                    <x-input-error :messages="$errors->get('email')" />
                </div>

                <div>
                    <x-label for="password" :value="__('Temporary password')" required />
                    <x-input
                        id="password"
                        wire:model="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                        :error="$errors->has('password')"
                    />
                    <x-input-error :messages="$errors->get('password')" />
                </div>

                <div>
                    <x-label for="password_confirmation" :value="__('Confirm password')" required />
                    <x-input
                        id="password_confirmation"
                        wire:model="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                        :error="$errors->has('password_confirmation')"
                    />
                    <x-input-error :messages="$errors->get('password_confirmation')" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-[#1e2433]">
                <x-button type="button" variant="secondary" wire:click="closeCreateModal" class="cursor-pointer">
                    {{ __('Cancel') }}
                </x-button>
                <x-button type="submit" class="cursor-pointer shadow-xs" wire:loading.attr="disabled">
                    <i class="fa-solid fa-shield-check text-xs" wire:loading.class="hidden" wire:target="createAdministrator"></i>
                    <i class="fa-solid fa-spinner fa-spin text-xs" wire:loading wire:target="createAdministrator"></i>
                    <span>{{ __('Create administrator') }}</span>
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Revoke Administrator Access Modal --}}
    <x-modal name="revoke-administrator-modal" :show="$showRevokeModal" maxWidth="md">
        <div class="p-6 space-y-4 text-center">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 text-rose-600 dark:bg-rose-950/80 dark:text-rose-400 flex items-center justify-center mx-auto text-lg">
                <i class="fa-solid fa-user-slash"></i>
            </div>

            <div class="space-y-1.5">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    {{ __('Revoke platform administrator access?') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                    {{ __('Are you sure you want to remove administrator privileges from') }}
                    <strong class="text-slate-800 dark:text-slate-200">{{ $this->targetUser?->name }}</strong>
                    ({{ $this->targetUser?->email }})?
                    {{ __('They will immediately lose access to the platform administration portal.') }}
                </p>
            </div>

            <div class="flex items-center justify-center gap-3 pt-3">
                <x-button
                    type="button"
                    variant="secondary"
                    wire:click="closeRevokeModal"
                    class="font-semibold text-xs cursor-pointer"
                >
                    {{ __('Cancel') }}
                </x-button>
                <x-button
                    type="button"
                    variant="danger"
                    wire:click="executeRevokeAdmin"
                    class="font-semibold text-xs shadow-xs cursor-pointer"
                    wire:loading.attr="disabled"
                >
                    <i class="fa-solid fa-user-minus text-xs" wire:loading.class="hidden" wire:target="executeRevokeAdmin"></i>
                    <i class="fa-solid fa-spinner fa-spin text-xs" wire:loading wire:target="executeRevokeAdmin"></i>
                    <span>{{ __('Revoke admin access') }}</span>
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
