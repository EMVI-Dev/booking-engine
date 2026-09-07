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
        session()->flash('success', __('We emailed them a link to set a password and join.'));
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

        session()->flash('success', __('They are no longer on your team.'));
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

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200" role="status">
                {{ session('success') }}
            </div>
        @endif

        <x-input-error :messages="$errors->get('team')" />

        @if ($this->currentOperator && ! $this->currentOperator->canAddTeamMember())
        <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-900 dark:bg-amber-950/40">
            <h2 class="text-sm font-bold text-amber-950 dark:text-amber-100">
                {{ __('Your team is full on this plan') }}
            </h2>
            <p class="mt-1 text-xs text-amber-800 dark:text-amber-200">
                {{ __(':seats. Move to Pro to add more people.', ['seats' => $this->currentOperator->getPlan()->teamSeatLabel()]) }}
            </p>
            <a href="{{ route('settings.plan') }}" wire:navigate class="mt-3 inline-flex text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                {{ __('See Pro') }}
            </a>
        </div>
        @else
        <div
            x-data="{ inviteOpen: {{ $errors->hasAny(['invite_name', 'invite_email', 'invite_role']) ? 'true' : 'false' }} }"
            class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xs dark:border-[#1e2433] dark:bg-[#0C0E13]"
        >
            <button
                type="button"
                x-on:click="inviteOpen = ! inviteOpen"
                class="flex w-full cursor-pointer items-center justify-between gap-4 p-5 text-left select-none transition-colors duration-200 hover:bg-slate-50/80 dark:hover:bg-[#141821]/60"
                :aria-expanded="inviteOpen"
                aria-controls="invite-someone-panel"
            >
                <div class="flex items-center gap-3.5">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-400 text-sm text-brand-foreground">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                            {{ __('Invite someone') }}
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('We email them a link to set their own password. You cannot invite another owner from here.') }}
                        </p>
                    </div>
                </div>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-slate-600 dark:border-[#1e2433] dark:bg-[#141821] dark:text-slate-300">
                    <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" :class="inviteOpen ? 'rotate-180' : ''" aria-hidden="true"></i>
                </span>
            </button>

            <div
                id="invite-someone-panel"
                x-show="inviteOpen"
                x-collapse
                x-cloak
                class="border-t border-slate-200/80 dark:border-[#1e2433]"
                style="display: none;"
            >
        <form wire:submit="promptInvite" class="space-y-5 p-6">

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-field :label="__('Name')" name="invite_name" required>
                    <x-input id="invite_name" wire:model="invite_name" type="text" :error="$errors->has('invite_name')" />
                </x-field>

                <x-field :label="__('Email')" name="invite_email" required>
                    <x-input id="invite_email" wire:model="invite_email" type="email" :error="$errors->has('invite_email')" />
                </x-field>
            </div>

            <div class="space-y-3">
                <div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('What they can do') }}</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
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
                            class="cursor-pointer rounded-2xl border p-4 text-left transition-colors duration-200 {{ $isSelected
                                ? 'border-brand-400 bg-amber-50/70 ring-2 ring-brand-400/30 dark:border-brand-400 dark:bg-amber-400/10'
                                : 'border-slate-200 bg-slate-50/40 hover:border-slate-300 hover:bg-slate-50 dark:border-[#1e2433] dark:bg-[#141821] dark:hover:border-zinc-600' }}"
                        >
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $isSelected
                                    ? 'bg-brand-400 text-brand-foreground'
                                    : 'bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400' }}">
                                    <i class="fa-solid {{ $role['icon'] }} text-sm" aria-hidden="true"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ __($role['label']) }}</p>
                                    <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
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
                                        <span class="{{ $area['allowed'] ? 'font-medium text-slate-700 dark:text-slate-200' : 'text-slate-400 dark:text-zinc-500' }}">
                                            {{ __($area['label']) }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($role['caveat'])
                                <p class="mt-3 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                    {{ __($role['caveat']) }}
                                </p>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <x-button variant="primary" type="submit">
                {{ __('Review invite') }}
            </x-button>
        </form>
            </div>
        </div>
        @endif

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xs dark:border-[#1e2433] dark:bg-[#0C0E13]">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-[#1e2433]">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('People on this team') }}
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
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
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs sm:text-sm">
                        <thead class="border-b border-slate-200/80 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:border-[#1e2433] dark:bg-[#10141d] dark:text-slate-500">
                            <tr>
                                <th class="px-5 py-3.5">{{ __('Person') }}</th>
                                <th class="px-5 py-3.5">{{ __('Job') }}</th>
                                <th class="px-5 py-3.5">{{ __('Desk access') }}</th>
                                <th class="px-5 py-3.5 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
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
                                <tr class="transition hover:bg-slate-50/60 dark:hover:bg-[#141824]/80" wire:key="teammate-{{ $member->id }}">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-brand-400 text-xs font-bold text-brand-foreground">
                                                {{ $initials }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-bold text-slate-900 dark:text-white">
                                                    {{ $member->name }}
                                                    @if ($member->id === auth()->id())
                                                        <span class="ml-1 text-[11px] font-semibold text-slate-400">{{ __('You') }}</span>
                                                    @endif
                                                </p>
                                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $member->email }}</p>
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
                                                        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $area['allowed']
                                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                            : 'border-slate-200 bg-slate-50 text-slate-400 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-500' }}"
                                                    >
                                                        <i class="fa-solid {{ $area['allowed'] ? 'fa-check' : 'fa-minus' }} text-[8px]" aria-hidden="true"></i>
                                                        {{ __($area['label']) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-400">{{ __('Unknown role') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        @if ($member->id !== auth()->id())
                                            <button
                                                type="button"
                                                wire:click="removeTeammate('{{ $member->id }}')"
                                                wire:confirm="{{ __('Remove them from your team?') }}"
                                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 transition-colors duration-200 hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2 dark:border-rose-800/70 dark:bg-rose-950/50 dark:text-rose-300 dark:hover:bg-rose-950/80"
                                            >
                                                <i class="fa-solid fa-user-minus text-[10px]" aria-hidden="true"></i>
                                                {{ __('Remove') }}
                                            </button>
                                        @else
                                            <span class="text-xs font-medium text-slate-400">{{ __('You') }}</span>
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
        <div class="space-y-4 p-6">
            <div class="flex items-start gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-400 text-brand-foreground">
                    <i class="fa-solid fa-envelope text-lg" aria-hidden="true"></i>
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">
                        {{ __('Send this invite?') }}
                    </h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ __('We will email a password link. They can sign in after they set it.') }}
                    </p>
                </div>
            </div>

            <dl class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-[#1e2433] dark:bg-[#141821]">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Name') }}</dt>
                    <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ $invite_name !== '' ? $invite_name : '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Email') }}</dt>
                    <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ $invite_email !== '' ? $invite_email : '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Job') }}</dt>
                    <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ $confirmRole?->label() ?? '—' }}</dd>
                </div>
            </dl>

            @if ($confirmRole)
                <div class="space-y-2">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('Desk access') }}</p>
                    <ul class="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                        @foreach ($confirmRole->deskScope() as $area)
                            <li class="flex items-start gap-2 text-xs" wire:key="confirm-scope-{{ $area['key'] }}">
                                <i
                                    class="fa-solid mt-0.5 {{ $area['allowed'] ? 'fa-check text-emerald-600 dark:text-emerald-400' : 'fa-minus text-slate-300 dark:text-zinc-600' }}"
                                    aria-hidden="true"
                                ></i>
                                <span class="{{ $area['allowed'] ? 'font-medium text-slate-700 dark:text-slate-200' : 'text-slate-400 dark:text-zinc-500' }}">
                                    {{ __($area['label']) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($confirmRole->inviteCaveat())
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                            {{ __($confirmRole->inviteCaveat()) }}
                        </p>
                    @endif
                </div>
            @endif

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'confirm-team-invite')" class="font-semibold text-xs">
                    {{ __('Cancel') }}
                </x-button>
                <x-button type="button" variant="primary" wire:click="inviteTeammate" wire:loading.attr="disabled" class="font-semibold text-xs shadow-xs">
                    <i class="fa-solid fa-paper-plane mr-1.5 text-xs" aria-hidden="true"></i>
                    {{ __('Send invite') }}
                </x-button>
            </div>
        </div>
    </x-modal>
</div>
