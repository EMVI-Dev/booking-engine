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
     * @return list<array{value: string, label: string, description: string}>
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
            ])
            ->values()
            ->all();
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

    public function inviteTeammate(): void
    {
        $this->authorizeAbility('manageTeam');

        $validated = $this->validate([
            'invite_name' => ['required', 'string', 'max:255'],
            'invite_email' => ['required', 'email', 'max:255'],
            'invite_role' => ['required', Rule::enum(OperatorUserRole::class)->except(OperatorUserRole::Owner)],
        ]);

        $operator = $this->currentOperator;
        abort_unless($operator, 403);

        if (! $operator->canAddTeamMember()) {
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
        unset($this->teammates);

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
        <x-settings-nav />

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
        <form wire:submit="inviteTeammate" class="space-y-4 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {{ __('Invite someone') }}
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('We email them a link to set their own password. You cannot invite another owner from here.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-field :label="__('Name')" name="invite_name" required>
                    <x-input id="invite_name" wire:model="invite_name" type="text" :error="$errors->has('invite_name')" />
                </x-field>

                <x-field :label="__('Email')" name="invite_email" required>
                    <x-input id="invite_email" wire:model="invite_email" type="email" :error="$errors->has('invite_email')" />
                </x-field>

                <x-field :label="__('What they can do')" name="invite_role" required>
                    <x-select
                        id="invite_role"
                        wire:model="invite_role"
                        :options="collect($this->inviteableRoles)->mapWithKeys(fn (array $role) => [$role['value'] => $role['label']])->all()"
                        :error="$errors->has('invite_role')"
                    />
                </x-field>
            </div>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach ($this->inviteableRoles as $role)
                    <p class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400" wire:key="role-help-{{ $role['value'] }}">
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $role['label'] }}:</span>
                        {{ $role['description'] }}
                    </p>
                @endforeach
            </div>

            <x-button variant="primary" type="submit">
                {{ __('Send invite') }}
            </x-button>
        </form>
        @endif

        <div class="space-y-3 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                {{ __('People on this team') }}
            </h2>

            @if ($this->teammates->isEmpty())
                <x-empty-state
                    icon="fa-users"
                    compact
                    :title="__('No one is on the team yet')"
                    :description="__('Invite a manager, a bookings person, or someone who handles money.')"
                />
            @else
                <ul class="divide-y divide-slate-100 dark:divide-zinc-800">
                    @foreach ($this->teammates as $member)
                        @php
                            $memberRole = $member->pivot->role instanceof \App\Enums\OperatorUserRole
                                ? $member->pivot->role
                                : \App\Enums\OperatorUserRole::tryFrom((string) $member->pivot->role);
                        @endphp
                        <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between" wire:key="teammate-{{ $member->id }}">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $member->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $member->email }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-badge variant="info" size="sm">
                                    {{ $memberRole?->label() ?? __('Team') }}
                                </x-badge>
                                @if ($member->id !== auth()->id())
                                    <x-button
                                        variant="ghost"
                                        size="sm"
                                        type="button"
                                        wire:click="removeTeammate('{{ $member->id }}')"
                                        wire:confirm="{{ __('Remove them from your team?') }}"
                                    >
                                        {{ __('Remove') }}
                                    </x-button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
