<?php

use App\Concerns\ResolvesCurrentOperator;
use App\Enums\OperatorUserRole;
use App\Models\User;
use App\Services\OperatorActivitySlackNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Your team')] class extends Component {
    use ResolvesCurrentOperator;

    public string $invite_name = '';

    public string $invite_email = '';

    public string $invite_role = '';

    public function mount(): void
    {
        $this->authorizeAbility('manageTeam');
        $this->invite_role = OperatorUserRole::Reservation->value;
    }

    /**
     * @return list<array{value: string, label: string, description: string, icon: string, caveat: ?string, scope: list<array{key: string, label: string, allowed: bool}>}>
     */
    #[Computed]
    public function inviteableRoles(): array
    {
        return collect(OperatorUserRole::cases())
            ->reject(fn (OperatorUserRole $role): bool => $role === OperatorUserRole::Owner)
            ->map(fn (OperatorUserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
                'icon' => $role->icon(),
                'caveat' => $role->inviteCaveat(),
                'scope' => $role->deskScope(),
            ])
            ->values()
            ->all();
    }

    #[Computed]
    public function selectedInviteRole(): ?OperatorUserRole
    {
        return OperatorUserRole::tryFrom($this->invite_role);
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    #[Computed]
    public function teammates()
    {
        return $this->currentOperator
            ?->users()
            ->orderBy('name')
            ->get() ?? collect();
    }

    /**
     * @return array{invite_name: string, invite_email: string, invite_role: string}
     */
    private function validateInvite(): array
    {
        return $this->validate([
            'invite_name' => ['required', 'string', 'max:255'],
            'invite_email' => ['required', 'email', 'max:255'],
            'invite_role' => ['required', Rule::enum(OperatorUserRole::class)->except(OperatorUserRole::Owner)],
        ]);
    }

    public function promptInvite(): void
    {
        $this->authorizeAbility('manageTeam');
        $this->validateInvite();
        $this->dispatch('open-modal', 'confirm-team-invite');
    }

    public function inviteTeammate(): void
    {
        $this->authorizeAbility('manageTeam');

        $validated = $this->validateInvite();

        $operator = $this->currentOperator;
        abort_unless($operator, 403);

        if (! $operator->canAddTeamMember()) {
            $this->dispatch('close-modal', 'confirm-team-invite');
            $this->addError('invite_email', __('Your free plan includes you and one helper. Move to Pro to add more people.'));

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => strtolower(trim($validated['invite_email']))],
            [
                'name' => $validated['invite_name'],
                'password' => Str::password(32),
            ],
        );

        if ($operator->users()->whereKey($user->id)->exists()) {
            $this->dispatch('close-modal', 'confirm-team-invite');
            $this->addError('invite_email', __('This person is already on your team.'));

            return;
        }

        $operator->users()->attach($user->id, [
            'role' => $validated['invite_role'],
        ]);

        Password::sendResetLink(['email' => $user->email]);

        app(OperatorActivitySlackNotifier::class)->teammateInvited(
            $operator,
            $user,
            OperatorUserRole::from($validated['invite_role'])->label(),
        );

        $this->reset('invite_name', 'invite_email');
        $this->invite_role = OperatorUserRole::Reservation->value;
        unset($this->teammates, $this->selectedInviteRole);

        $this->dispatch('close-modal', 'confirm-team-invite');
        $this->dispatch(
            'toast',
            message: __('We emailed them a link to set a password and join.'),
            type: 'success',
        );
    }

    public function removeTeammate(string $userId): void
    {
        $this->authorizeAbility('manageTeam');

        $operator = $this->currentOperator;
        abort_unless($operator, 403);

        $member = $operator->users()->whereKey($userId)->first();

        if (! $member) {
            return;
        }

        $role = $member->pivot->role instanceof OperatorUserRole
            ? $member->pivot->role
            : OperatorUserRole::tryFrom((string) $member->pivot->role);

        if ($role === OperatorUserRole::Owner) {
            $ownerCount = $operator->users()
                ->wherePivot('role', OperatorUserRole::Owner->value)
                ->count();

            if ($ownerCount <= 1) {
                $this->addError('team', __('Every business needs at least one owner.'));

                return;
            }

            if (Auth::user()?->roleOn($operator) !== OperatorUserRole::Owner) {
                $this->addError('team', __('Only the owner can remove another owner.'));

                return;
            }
        }

        $operator->users()->detach($userId);
        unset($this->teammates);

        $this->dispatch(
            'toast',
            message: __('They are no longer on your team.'),
            type: 'success',
        );
    }
}; ?>

<div class="space-y-6 w-full">
    <x-desktop-only-notice
        :title="__('Your team is easier to manage on a computer')"
        :description="__('Invite people and choose what they can do from a larger screen.')"
    />

    <div class="hidden lg:block space-y-6">
        <x-page-header
            :title="__('Your team')"
            :subtitle="__('People who help run this business. Each person only sees what their job needs.')"
            icon="fa-users"
        />

        <x-input-error :messages="$errors->get('team')" />

        @if ($this->currentOperator && ! $this->currentOperator->canAddTeamMember())
        <div class="rounded-[12px] border border-amber-300/80 bg-amber-50/70 p-5 dark:border-amber-900/50 dark:bg-amber-950/20 shadow-none">
            <h2 class="text-sm font-bold text-amber-950 dark:text-amber-100">
                {{ __('Your team is full on this plan') }}
            </h2>
            <p class="mt-1 text-xs text-amber-800 dark:text-amber-200">
                {{ __(':seats. Move to Pro to add more people.', ['seats' => $this->currentOperator->getPlan()->teamSeatLabel()]) }}
            </p>
            <a href="{{ route('settings.plan') }}" wire:navigate class="mt-3 inline-flex text-xs font-semibold text-slate-900 dark:text-[#FFEF4D] hover:underline">
                {{ __('See Pro') }}
            </a>
        </div>
        @else
        <div
            x-data="{ inviteOpen: {{ $errors->hasAny(['invite_name', 'invite_email', 'invite_role']) ? 'true' : 'false' }} }"
            class="overflow-hidden rounded-[12px] border border-[#E4E5E9] bg-white shadow-none dark:border-[#1E2433] dark:bg-[#10141d]"
        >
            <button
                type="button"
                x-on:click="inviteOpen = ! inviteOpen"
                class="flex w-full cursor-pointer items-center justify-between gap-4 p-4 sm:p-5 text-left select-none transition hover:bg-[#F4F5F7]/80 dark:hover:bg-[#141821]/60"
                :aria-expanded="inviteOpen"
                aria-controls="invite-someone-panel"
            >
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[8px] bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] border border-[#FFEF4D]/40 text-sm font-semibold">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Invite someone') }}
                        </h2>
                        <p class="mt-0.5 text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __('We email them a link to set their own password. You cannot invite another owner from here.') }}
                        </p>
                    </div>
                </div>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] border border-[#E4E5E9] bg-[#F4F5F7] text-[#5A6578] dark:border-[#1E2433] dark:bg-[#141821] dark:text-[#9DA4B2]">
                    <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" :class="inviteOpen ? 'rotate-180' : ''" aria-hidden="true"></i>
                </span>
            </button>

            <div
                id="invite-someone-panel"
                x-show="inviteOpen"
                x-collapse
                x-cloak
                class="border-t border-[#E4E5E9] dark:border-[#1E2433]"
                style="display: none;"
            >
                <form wire:submit="promptInvite" class="space-y-5 p-4 sm:p-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-label for="invite_name" :value="__('Name')" required />
                            <x-input id="invite_name" wire:model="invite_name" type="text" class="rounded-[6px] h-9 text-xs" :error="$errors->has('invite_name')" />
                            <x-input-error :messages="$errors->get('invite_name')" />
                        </div>

                        <div>
                            <x-label for="invite_email" :value="__('Email')" required />
                            <x-input id="invite_email" wire:model="invite_email" type="email" class="rounded-[6px] h-9 text-xs" :error="$errors->has('invite_email')" />
                            <x-input-error :messages="$errors->get('invite_email')" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">{{ __('What they can do') }}</p>
                            <p class="mt-0.5 text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                                {{ __('Pick a job. Each one only opens the parts of the desk they need.') }}
                            </p>
                            <x-input-error :messages="$errors->get('invite_role')" />
                        </div>

                        <div class="grid grid-cols-1 gap-3 xl:grid-cols-3" role="radiogroup" aria-label="{{ __('What they can do') }}">
                            @foreach ($this->inviteableRoles as $role)
                                @php
                                    $isSelected = $invite_role === $role['value'];
                                @endphp
                                <button
                                    type="button"
                                    wire:key="invite-role-{{ $role['value'] }}"
                                    wire:click="$set('invite_role', '{{ $role['value'] }}')"
                                    role="radio"
                                    aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                                    class="cursor-pointer rounded-[8px] border p-3.5 text-left transition shadow-none {{ $isSelected
                                        ? 'border-[#12181E] bg-[#FFEF4D]/15 dark:border-[#FFEF4D] dark:bg-[#FFEF4D]/10'
                                        : 'border-[#E4E5E9] bg-[#F4F5F7]/40 hover:bg-[#F4F5F7] dark:border-[#1E2433] dark:bg-[#141821] dark:hover:bg-[#141821]/80' }}"
                                >
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] {{ $isSelected
                                            ? 'bg-[#FFEF4D] text-[#12181E]'
                                            : 'bg-[#E4E5E9]/60 text-slate-600 dark:bg-[#1E2433] dark:text-zinc-400' }}">
                                            <i class="fa-solid {{ $role['icon'] }} text-xs" aria-hidden="true"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-slate-900 dark:text-white">{{ __($role['label']) }}</p>
                                            <p class="mt-0.5 text-[11px] leading-relaxed text-[#5A6578] dark:text-[#9DA4B2]">
                                                {{ __($role['description']) }}
                                            </p>
                                        </div>
                                    </div>

                                    <ul class="mt-3 space-y-1.5">
                                        @foreach ($role['scope'] as $area)
                                            <li class="flex items-start gap-2 text-[11px] leading-snug" wire:key="invite-scope-{{ $role['value'] }}-{{ $area['key'] }}">
                                                <i
                                                    class="fa-solid mt-0.5 {{ $area['allowed'] ? 'fa-check text-emerald-600 dark:text-emerald-400' : 'fa-minus text-slate-300 dark:text-zinc-600' }}"
                                                    aria-hidden="true"
                                                ></i>
                                                <span class="{{ $area['allowed'] ? 'font-medium text-slate-700 dark:text-slate-200' : 'text-[#5A6578] dark:text-[#9DA4B2]' }}">
                                                    {{ __($area['label']) }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>

                                    @if ($role['caveat'])
                                        <p class="mt-2.5 text-[11px] font-medium text-[#5A6578] dark:text-[#9DA4B2]">
                                            {{ __($role['caveat']) }}
                                        </p>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-2">
                        <x-button variant="primary" type="submit" size="sm" class="shadow-none font-semibold">
                            {{ __('Review invite') }}
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        <div class="overflow-hidden rounded-[12px] border border-[#E4E5E9] bg-white shadow-none dark:border-[#1E2433] dark:bg-[#10141d]">
            <div class="flex items-center justify-between gap-3 border-b border-[#E4E5E9] px-4 py-3.5 sm:px-5 sm:py-4 dark:border-[#1E2433]">
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('People on this team') }}
                    </h2>
                    <p class="mt-0.5 text-xs text-[#5A6578] dark:text-[#9DA4B2]">
                        {{ trans_choice(':count person|:count people', $this->teammates->count()) }}
                    </p>
                </div>
            </div>

            @if ($this->teammates->isEmpty())
                <div class="p-6">
                    <x-empty-state
                        icon="fa-users"
                        compact
                        :title="__('No one is on the team yet')"
                        :description="__('Invite a manager, a bookings person, or someone who handles money.')"
                    />
                </div>
            @else
                <!-- Mobile Card View -->
                <div class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433] md:hidden">
                    @foreach ($this->teammates as $member)
                        @php
                            $memberRole = $member->pivot->role instanceof \App\Enums\OperatorUserRole
                                ? $member->pivot->role
                                : \App\Enums\OperatorUserRole::tryFrom((string) $member->pivot->role);
                            $initials = strtoupper(substr($member->name, 0, 2));
                            $roleBadgeVariant = match ($memberRole) {
                                \App\Enums\OperatorUserRole::Owner => 'warning',
                                \App\Enums\OperatorUserRole::Admin => 'info',
                                \App\Enums\OperatorUserRole::Reservation => 'success',
                                \App\Enums\OperatorUserRole::Finance => 'neutral',
                                default => 'neutral',
                            };
                        @endphp
                        <div class="p-4 space-y-3" wire:key="teammate-mobile-{{ $member->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] bg-[#FFEF4D]/20 text-xs font-bold text-[#12181E] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                                        {{ $initials }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900 dark:text-white">
                                            {{ $member->name }}
                                            @if ($member->id === auth()->id())
                                                <span class="ml-1 text-[11px] font-semibold text-[#5A6578] dark:text-[#9DA4B2]">({{ __('You') }})</span>
                                            @endif
                                        </p>
                                        <p class="truncate text-xs text-[#5A6578] dark:text-[#9DA4B2]">{{ $member->email }}</p>
                                    </div>
                                </div>

                                <x-badge :variant="$roleBadgeVariant" size="xs">
                                    {{ $memberRole?->label() ?? __('Team') }}
                                </x-badge>
                            </div>

                            @if ($memberRole)
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($memberRole->deskScope() as $area)
                                        <span
                                            wire:key="member-scope-mob-{{ $member->id }}-{{ $area['key'] }}"
                                            class="inline-flex items-center gap-1 rounded-[4px] border px-1.5 py-0.5 text-[10px] font-medium {{ $area['allowed']
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                : 'border-[#E4E5E9] bg-[#F4F5F7] text-[#5A6578] dark:border-[#1E2433] dark:bg-[#141821] dark:text-[#9DA4B2]' }}"
                                        >
                                            <i class="fa-solid {{ $area['allowed'] ? 'fa-check' : 'fa-minus' }} text-[7px]" aria-hidden="true"></i>
                                            {{ __($area['label']) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($member->id !== auth()->id())
                                <div class="pt-1 flex justify-end">
                                    <button
                                        type="button"
                                        wire:click="removeTeammate('{{ $member->id }}')"
                                        wire:confirm="{{ __('Remove them from your team?') }}"
                                        class="inline-flex cursor-pointer items-center gap-1 rounded-[6px] border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-800/70 dark:bg-rose-950/50 dark:text-rose-300 shadow-none"
                                    >
                                        <i class="fa-solid fa-user-minus text-[10px]" aria-hidden="true"></i>
                                        <span>{{ __('Remove') }}</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Desktop Table View -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="border-b border-[#E4E5E9] bg-[#F4F5F7]/80 text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:border-[#1E2433] dark:bg-[#141821] dark:text-[#9DA4B2]">
                            <tr>
                                <th class="px-5 py-3.5">{{ __('Person') }}</th>
                                <th class="px-5 py-3.5">{{ __('Job') }}</th>
                                <th class="px-5 py-3.5">{{ __('Desk access') }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                            @foreach ($this->teammates as $member)
                                @php
                                    $memberRole = $member->pivot->role instanceof \App\Enums\OperatorUserRole
                                        ? $member->pivot->role
                                        : \App\Enums\OperatorUserRole::tryFrom((string) $member->pivot->role);
                                    $initials = strtoupper(substr($member->name, 0, 2));
                                    $roleBadgeVariant = match ($memberRole) {
                                        \App\Enums\OperatorUserRole::Owner => 'warning',
                                        \App\Enums\OperatorUserRole::Admin => 'info',
                                        \App\Enums\OperatorUserRole::Reservation => 'success',
                                        \App\Enums\OperatorUserRole::Finance => 'neutral',
                                        default => 'neutral',
                                    };
                                @endphp
                                <tr class="transition hover:bg-[#F4F5F7]/50 dark:hover:bg-[#141821]/50" wire:key="teammate-{{ $member->id }}">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] bg-[#FFEF4D]/20 text-xs font-bold text-[#12181E] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                                                {{ $initials }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-bold text-slate-900 dark:text-white">
                                                    {{ $member->name }}
                                                    @if ($member->id === auth()->id())
                                                        <span class="ml-1 text-[11px] font-semibold text-[#5A6578] dark:text-[#9DA4B2]">{{ __('You') }}</span>
                                                    @endif
                                                </p>
                                                <p class="truncate text-xs text-[#5A6578] dark:text-[#9DA4B2]">{{ $member->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <x-badge :variant="$roleBadgeVariant" size="sm">
                                            {{ $memberRole?->label() ?? __('Team') }}
                                        </x-badge>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($memberRole)
                                            <div class="flex max-w-xl flex-wrap gap-1.5">
                                                @foreach ($memberRole->deskScope() as $area)
                                                    <span
                                                        wire:key="member-scope-{{ $member->id }}-{{ $area['key'] }}"
                                                        class="inline-flex items-center gap-1 rounded-[4px] border px-2 py-0.5 text-[10px] font-medium {{ $area['allowed']
                                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                            : 'border-[#E4E5E9] bg-[#F4F5F7] text-[#5A6578] dark:border-[#1E2433] dark:bg-[#141821] dark:text-[#9DA4B2]' }}"
                                                    >
                                                        <i class="fa-solid {{ $area['allowed'] ? 'fa-check' : 'fa-minus' }} text-[8px]" aria-hidden="true"></i>
                                                        {{ __($area['label']) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-xs text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Unknown role') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        @if ($member->id !== auth()->id())
                                            <button
                                                type="button"
                                                wire:click="removeTeammate('{{ $member->id }}')"
                                                wire:confirm="{{ __('Remove them from your team?') }}"
                                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-[6px] border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-800/70 dark:bg-rose-950/50 dark:text-rose-300 shadow-none"
                                            >
                                                <i class="fa-solid fa-user-minus text-[10px]" aria-hidden="true"></i>
                                                <span>{{ __('Remove') }}</span>
                                            </button>
                                        @else
                                            <span class="text-xs font-medium text-[#5A6578] dark:text-[#9DA4B2]">{{ __('You') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <x-modal name="confirm-team-invite" maxWidth="lg">
        @php
            $confirmRole = $this->selectedInviteRole;
        @endphp
        <div class="space-y-4 p-5 sm:p-6">
            <!-- Mobile drag handle -->
            <div class="mx-auto -mt-1 mb-2 h-1 w-10 shrink-0 rounded-full bg-[#E4E5E9] dark:bg-[#1E2433] sm:hidden"></div>

            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-[#FFEF4D]/20 text-[#12181E] dark:text-[#FFEF4D] border border-[#FFEF4D]/40">
                    <i class="fa-solid fa-envelope text-sm" aria-hidden="true"></i>
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">
                        {{ __('Send this invite?') }}
                    </h3>
                    <p class="mt-0.5 text-xs leading-relaxed text-[#5A6578] dark:text-[#9DA4B2]">
                        {{ __('We will email a password link. They can sign in after they set it.') }}
                    </p>
                </div>
            </div>

            <dl class="space-y-3 rounded-[8px] border border-[#E4E5E9] bg-[#F4F5F7]/70 p-4 dark:border-[#1E2433] dark:bg-[#141821] shadow-none">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Name') }}</dt>
                    <dd class="text-xs font-semibold text-slate-900 dark:text-white">{{ $invite_name !== '' ? $invite_name : '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Email') }}</dt>
                    <dd class="text-xs font-semibold text-slate-900 dark:text-white">{{ $invite_email !== '' ? $invite_email : '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Job') }}</dt>
                    <dd class="text-xs font-semibold text-slate-900 dark:text-white">{{ $confirmRole?->label() ?? '—' }}</dd>
                </div>
            </dl>

            @if ($confirmRole)
                <div class="space-y-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#5A6578] dark:text-[#9DA4B2]">{{ __('Desk access') }}</p>
                    <ul class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                        @foreach ($confirmRole->deskScope() as $area)
                            <li class="flex items-start gap-2 text-xs" wire:key="confirm-scope-{{ $area['key'] }}">
                                <i
                                    class="fa-solid mt-0.5 {{ $area['allowed'] ? 'fa-check text-emerald-600 dark:text-emerald-400' : 'fa-minus text-slate-300 dark:text-zinc-600' }}"
                                    aria-hidden="true"
                                ></i>
                                <span class="{{ $area['allowed'] ? 'font-medium text-slate-700 dark:text-slate-200' : 'text-[#5A6578] dark:text-[#9DA4B2]' }}">
                                    {{ __($area['label']) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($confirmRole->inviteCaveat())
                        <p class="text-xs font-medium text-[#5A6578] dark:text-[#9DA4B2]">
                            {{ __($confirmRole->inviteCaveat()) }}
                        </p>
                    @endif
                </div>
            @endif

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                <x-button type="button" variant="secondary" size="sm" x-on:click="$dispatch('close-modal', 'confirm-team-invite')" class="font-semibold text-xs">
                    {{ __('Cancel') }}
                </x-button>
                <x-button type="button" variant="primary" size="sm" wire:click="inviteTeammate" wire:loading.attr="disabled" class="font-semibold text-xs shadow-none">
                    <i class="fa-solid fa-paper-plane mr-1.5 text-xs" aria-hidden="true"></i>
                    {{ __('Send invite') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
