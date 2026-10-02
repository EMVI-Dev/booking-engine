<?php

use App\Concerns\RecordsAdminActions;
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
    use RecordsAdminActions;

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

        $admin = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);

        $this->audit('admin.created', $admin, ['email' => $admin->email]);

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
            $this->audit('admin.revoked', $user, ['email' => $user->email]);
            $this->dispatch('toast', message: __(':name has been removed from platform administrators.', ['name' => $user->name]), type: 'success');
        }

        $this->closeRevokeModal();
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <x-page-header
        :title="__('Platform Administrators')"
        :subtitle="__('Manage platform staff and credentials with full administrative access.')"
        icon="fa-shield-halved"
        class="pb-2 border-b border-[#E4E5E9] dark:border-[#1E2433]"
    >
        <x-slot:actions>
            <button
                type="button"
                wire:click="openCreateModal"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-xs font-medium cursor-pointer transition shadow-none"
            >
                <i class="fa-solid fa-user-plus text-xs"></i>
                <span>{{ __('Add administrator') }}</span>
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- Security Metrics Strip --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-[12px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] p-4 shadow-none">
            <div class="flex items-center justify-between">
                <span class="text-[12px] font-medium text-[#60646C] dark:text-zinc-400">{{ __('Total administrators') }}</span>
                <span class="w-7 h-7 rounded-[6px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] flex items-center justify-center text-xs">
                    <i class="fa-solid fa-user-shield"></i>
                </span>
            </div>
            <div class="mt-2 text-[20px] font-semibold text-[#1C2024] dark:text-white">
                {{ $this->metrics['total'] }}
            </div>
            <p class="mt-1 text-[11px] text-[#60646C] dark:text-zinc-400">
                {{ __('Staff with platform command access') }}
            </p>
        </div>

        <div class="rounded-[12px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] p-4 shadow-none">
            <div class="flex items-center justify-between">
                <span class="text-[12px] font-medium text-[#60646C] dark:text-zinc-400">{{ __('Two-factor enabled') }}</span>
                <span class="w-7 h-7 rounded-[6px] {{ $this->metrics['two_factor'] === $this->metrics['total'] ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400' : 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400' }} flex items-center justify-center text-xs">
                    <i class="fa-solid fa-lock"></i>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-[20px] font-semibold text-[#1C2024] dark:text-white">
                    {{ $this->metrics['two_factor'] }}
                </span>
                <span class="text-xs text-[#60646C] dark:text-zinc-400">
                    / {{ $this->metrics['total'] }} {{ __('admins') }}
                </span>
            </div>
            <p class="mt-1 text-[11px] text-[#60646C] dark:text-zinc-400">
                {{ __('Accounts protected by 2FA') }}
            </p>
        </div>

        <div class="rounded-[12px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] p-4 shadow-none">
            <div class="flex items-center justify-between">
                <span class="text-[12px] font-medium text-[#60646C] dark:text-zinc-400">{{ __('Passkeys configured') }}</span>
                <span class="w-7 h-7 rounded-[6px] bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-fingerprint"></i>
                </span>
            </div>
            <div class="mt-2 text-[20px] font-semibold text-[#1C2024] dark:text-white">
                {{ $this->metrics['passkeys'] }}
            </div>
            <p class="mt-1 text-[11px] text-[#60646C] dark:text-zinc-400">
                {{ __('Accounts with biometric passkeys') }}
            </p>
        </div>
    </div>

    {{-- Search Toolbar --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="relative w-full sm:w-72">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[#60646C] dark:text-zinc-400"></i>
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="{{ __('Search by name or email...') }}"
                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
            />
        </div>
    </div>

    {{-- Administrators Table Card --}}
    <div class="rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none overflow-hidden">
        {{-- Mobile Responsive Card List (md:hidden) --}}
        <div class="md:hidden divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
            @forelse ($this->administrators as $admin)
                @php
                    $isCurrentUser = $admin->id === Auth::id();
                    $has2fa = filled($admin->two_factor_confirmed_at);
                @endphp
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-[6px] bg-[#FFEF4D] text-[#12181E] font-semibold flex items-center justify-center text-xs shrink-0">
                                {{ strtoupper(substr($admin->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-medium text-sm text-[#1C2024] dark:text-white truncate">
                                        {{ $admin->name }}
                                    </span>
                                    @if ($isCurrentUser)
                                        <span class="px-1.5 py-0.5 rounded-[4px] text-[10px] font-medium bg-[#FFEF4D]/30 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                                            {{ __('You') }}
                                        </span>
                                    @endif
                                </div>
                                <span class="text-xs text-[#60646C] dark:text-zinc-400 block truncate">
                                    {{ $admin->email }}
                                </span>
                            </div>
                        </div>

                        @if (! $isCurrentUser && $this->metrics['total'] > 1)
                            <button
                                type="button"
                                wire:click="confirmRevokeAdmin('{{ $admin->id }}')"
                                class="p-1.5 rounded-[6px] text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer text-xs"
                                title="{{ __('Revoke admin access') }}"
                            >
                                <i class="fa-solid fa-user-minus"></i>
                            </button>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 flex-wrap text-xs pt-1">
                        @if ($has2fa)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/40">
                                <i class="fa-solid fa-shield-check text-[10px]"></i>
                                <span>{{ __('2FA Active') }}</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/40">
                                <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                                <span>{{ __('2FA Disabled') }}</span>
                            </span>
                        @endif

                        @if ($admin->passkeys_count > 0)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200/80 dark:border-blue-800/40">
                                <i class="fa-solid fa-fingerprint text-[10px]"></i>
                                <span>{{ trans_choice('{1} :count Passkey|[2,*] :count Passkeys', $admin->passkeys_count) }}</span>
                            </span>
                        @endif

                        <span class="text-[11px] text-[#60646C] dark:text-zinc-400 ml-auto font-mono">
                            {{ $admin->created_at?->format('M j, Y') }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center text-[#60646C] dark:text-zinc-400">
                    <i class="fa-solid fa-user-shield text-2xl mb-2 block opacity-40"></i>
                    <p class="font-medium text-xs">{{ __('No administrators found.') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Desktop Table (hidden md:table) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821] font-medium text-[11px] text-[#60646C] dark:text-zinc-400 uppercase tracking-wider">
                        <th class="py-3 px-5">{{ __('Administrator') }}</th>
                        <th class="py-3 px-4">{{ __('2FA Security') }}</th>
                        <th class="py-3 px-4">{{ __('Passkeys') }}</th>
                        <th class="py-3 px-4">{{ __('Added') }}</th>
                        <th class="py-3 px-5 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                    @forelse ($this->administrators as $admin)
                        @php
                            $isCurrentUser = $admin->id === Auth::id();
                            $has2fa = filled($admin->two_factor_confirmed_at);
                        @endphp
                        <tr class="hover:bg-[#F4F5F6]/60 dark:hover:bg-[#141821]/60 transition">
                            <td class="py-3 px-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-[6px] bg-[#FFEF4D] text-[#12181E] font-semibold flex items-center justify-center text-xs shrink-0">
                                        {{ strtoupper(substr($admin->name, 0, 2)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-medium text-xs text-[#1C2024] dark:text-white truncate">
                                                {{ $admin->name }}
                                            </span>
                                            @if ($isCurrentUser)
                                                <span class="px-1.5 py-0.5 rounded-[4px] text-[10px] font-medium bg-[#FFEF4D]/30 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                                                    {{ __('You') }}
                                                </span>
                                            @endif
                                        </div>
                                        <span class="text-[11px] text-[#60646C] dark:text-zinc-400 block truncate">
                                            {{ $admin->email }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                @if ($has2fa)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/40">
                                        <i class="fa-solid fa-shield-check text-[10px]"></i>
                                        <span>{{ __('Active') }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/40">
                                        <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                                        <span>{{ __('Disabled') }}</span>
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if ($admin->passkeys_count > 0)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200/80 dark:border-blue-800/40">
                                        <i class="fa-solid fa-fingerprint text-[10px]"></i>
                                        <span>{{ trans_choice('{1} :count passkey|[2,*] :count passkeys', $admin->passkeys_count) }}</span>
                                    </span>
                                @else
                                    <span class="text-[#60646C] dark:text-zinc-400 text-[11px]">
                                        {{ __('None') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-[#60646C] dark:text-zinc-400 font-mono text-[11px]">
                                {{ $admin->created_at?->format('d M Y') }}
                            </td>
                            <td class="py-3 px-5 text-right">
                                @if ($isCurrentUser)
                                    <span class="text-[#60646C] dark:text-zinc-400 text-[11px] italic">
                                        {{ __('Current session') }}
                                    </span>
                                @elseif ($this->metrics['total'] > 1)
                                    <button
                                        type="button"
                                        wire:click="confirmRevokeAdmin('{{ $admin->id }}')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-[6px] text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-transparent hover:border-rose-200 dark:hover:border-rose-900/50 transition cursor-pointer"
                                    >
                                        <i class="fa-solid fa-user-minus text-[11px]"></i>
                                        <span>{{ __('Revoke') }}</span>
                                    </button>
                                @else
                                    <span class="text-[#60646C] dark:text-zinc-400 text-[11px] italic">
                                        {{ __('Sole admin') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-[#60646C] dark:text-zinc-400">
                                <i class="fa-solid fa-user-shield text-2xl mb-2 block opacity-40"></i>
                                <p class="font-medium text-xs">{{ __('No administrators found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->administrators->hasPages())
            <div class="p-3 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                {{ $this->administrators->links() }}
            </div>
        @endif
    </div>

    {{-- Add Administrator Modal --}}
    <x-modal name="add-administrator-modal" :show="$showCreateModal" maxWidth="md">
        <form wire:submit="createAdministrator" class="p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                <div class="flex items-center gap-2.5">
                    <span class="p-1.5 rounded-[6px] bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 text-xs">
                        <i class="fa-solid fa-user-plus"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-[#1C2024] dark:text-white">
                            {{ __('Add platform administrator') }}
                        </h3>
                        <p class="text-xs text-[#60646C] dark:text-zinc-400">
                            {{ __('Grants full access to operator management, plans, and platform config.') }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    wire:click="closeCreateModal"
                    class="text-[#60646C] hover:text-[#1C2024] dark:hover:text-white transition cursor-pointer"
                >
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-xs font-medium text-[#1C2024] dark:text-white mb-1.5">
                        {{ __('Full name') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="name"
                        wire:model="name"
                        type="text"
                        required
                        placeholder="{{ __('e.g. Sarah Connor') }}"
                        class="w-full px-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
                    />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <label for="email" class="block text-xs font-medium text-[#1C2024] dark:text-white mb-1.5">
                        {{ __('Email address') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="email"
                        wire:model="email"
                        type="email"
                        required
                        placeholder="{{ __('e.g. sarah@platform.com') }}"
                        class="w-full px-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
                    />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div>
                    <label for="password" class="block text-xs font-medium text-[#1C2024] dark:text-white mb-1.5">
                        {{ __('Temporary password') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="password"
                        wire:model="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                        class="w-full px-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
                    />
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-medium text-[#1C2024] dark:text-white mb-1.5">
                        {{ __('Confirm password') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="password_confirmation"
                        wire:model="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                        class="w-full px-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
                    />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                <button
                    type="button"
                    wire:click="closeCreateModal"
                    class="px-3 py-1.5 text-xs font-medium rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] transition cursor-pointer shadow-none"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-xs font-medium cursor-pointer transition shadow-none"
                    wire:loading.attr="disabled"
                >
                    <i class="fa-solid fa-shield-check text-xs" wire:loading.class="hidden" wire:target="createAdministrator"></i>
                    <i class="fa-solid fa-spinner fa-spin text-xs" wire:loading wire:target="createAdministrator"></i>
                    <span>{{ __('Create administrator') }}</span>
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Revoke Administrator Access Modal --}}
    <x-modal name="revoke-administrator-modal" :show="$showRevokeModal" maxWidth="md">
        <div class="p-6 space-y-4 text-center">
            <div class="w-10 h-10 rounded-[8px] bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400 flex items-center justify-center mx-auto text-base">
                <i class="fa-solid fa-user-slash"></i>
            </div>

            <div class="space-y-1">
                <h3 class="text-sm font-semibold text-[#1C2024] dark:text-white">
                    {{ __('Revoke platform administrator access?') }}
                </h3>
                <p class="text-xs text-[#60646C] dark:text-zinc-400 max-w-sm mx-auto leading-relaxed">
                    {{ __('Are you sure you want to remove administrator privileges from') }}
                    <strong class="text-[#1C2024] dark:text-white font-medium">{{ $this->targetUser?->name }}</strong>
                    ({{ $this->targetUser?->email }})?
                    {{ __('They will immediately lose access to the platform administration portal.') }}
                </p>
            </div>

            <div class="flex items-center justify-center gap-2 pt-2">
                <button
                    type="button"
                    wire:click="closeRevokeModal"
                    class="px-3 py-1.5 text-xs font-medium rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#141821] text-[#1C2024] dark:text-white hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] transition cursor-pointer shadow-none"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    wire:click="executeRevokeAdmin"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[6px] bg-rose-600 hover:bg-rose-700 text-white text-xs font-medium cursor-pointer transition shadow-none"
                    wire:loading.attr="disabled"
                >
                    <i class="fa-solid fa-user-minus text-xs" wire:loading.class="hidden" wire:target="executeRevokeAdmin"></i>
                    <i class="fa-solid fa-spinner fa-spin text-xs" wire:loading wire:target="executeRevokeAdmin"></i>
                    <span>{{ __('Revoke admin access') }}</span>
                </button>
            </div>
        </div>
    </x-modal>
</div>
