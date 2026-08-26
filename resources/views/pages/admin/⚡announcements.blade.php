<?php

use App\Models\Plan;
use App\Models\PlatformAnnouncement;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Platform Broadcast Notices')] #[Layout('layouts.admin')] class extends Component {
    public bool $show_modal = false;
    public ?string $editing_id = null;

    public string $title = '';
    public string $message = '';
    public string $type = 'info'; // 'info', 'warning', 'critical', 'success'
    public ?string $target_plan_id = null;
    public bool $is_active = true;
    public bool $is_dismissible = true;
    public ?string $starts_at = null;
    public ?string $ends_at = null;

    public string $filter_type = 'all';

    public function openCreateModal(): void
    {
        $this->editing_id = null;
        $this->title = '';
        $this->message = '';
        $this->type = 'info';
        $this->target_plan_id = null;
        $this->is_active = true;
        $this->is_dismissible = true;
        $this->starts_at = now()->format('Y-m-d\TH:i');
        $this->ends_at = now()->addDays(7)->format('Y-m-d\TH:i');
        $this->show_modal = true;
    }

    public function editAnnouncement(string $id): void
    {
        $announcement = PlatformAnnouncement::find($id);

        if (! $announcement) {
            return;
        }

        $this->editing_id = $announcement->id;
        $this->title = $announcement->title;
        $this->message = $announcement->message;
        $this->type = $announcement->type;
        $this->target_plan_id = $announcement->target_plan_id;
        $this->is_active = $announcement->is_active;
        $this->is_dismissible = $announcement->is_dismissible;
        $this->starts_at = $announcement->starts_at?->format('Y-m-d\TH:i');
        $this->ends_at = $announcement->ends_at?->format('Y-m-d\TH:i');
        $this->show_modal = true;
    }

    public function closeModal(): void
    {
        $this->show_modal = false;
        $this->editing_id = null;
    }

    public function saveAnnouncement(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'type' => ['required', 'in:info,warning,critical,success'],
            'target_plan_id' => ['nullable', 'exists:plans,id'],
            'is_active' => ['boolean'],
            'is_dismissible' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $attributes = [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'target_plan_id' => $this->target_plan_id ?: null,
            'is_active' => $this->is_active,
            'is_dismissible' => $this->is_dismissible,
            'starts_at' => $this->starts_at ? Carbon::parse($this->starts_at) : null,
            'ends_at' => $this->ends_at ? Carbon::parse($this->ends_at) : null,
        ];

        if ($this->editing_id) {
            PlatformAnnouncement::where('id', $this->editing_id)->update($attributes);
            session()->flash('success', __('Announcement updated successfully!'));
        } else {
            PlatformAnnouncement::create($attributes);
            session()->flash('success', __('New announcement broadcasted successfully!'));
        }

        $this->closeModal();
    }

    public function toggleActive(string $id): void
    {
        $announcement = PlatformAnnouncement::find($id);
        if ($announcement) {
            $announcement->update(['is_active' => ! $announcement->is_active]);
            session()->flash('success', __('Broadcast status toggled.'));
        }
    }

    public function deleteAnnouncement(string $id): void
    {
        PlatformAnnouncement::where('id', $id)->delete();
        session()->flash('success', __('Announcement removed.'));
    }

    public function render()
    {
        $query = PlatformAnnouncement::with('targetPlan')->latest('created_at');

        if ($this->filter_type !== 'all') {
            $query->where('type', $this->filter_type);
        }

        $announcements = $query->get();
        $plans = Plan::orderBy('sort_order')->get();
        $activeCount = PlatformAnnouncement::active()->count();

        return view('pages.admin.⚡announcements', [
            'announcements' => $announcements,
            'plans' => $plans,
            'activeCount' => $activeCount,
        ]);
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="p-2.5 rounded-2xl bg-purple-100 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300">
                <i class="fa-solid fa-bullhorn text-lg"></i>
            </span>
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ __('Platform Broadcasts & Announcements') }}
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Publish system banners, maintenance notices, and feature rollouts to operator portals.') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="openCreateModal"
                class="h-9 px-3.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-xs transition flex items-center gap-1.5 cursor-pointer"
            >
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>{{ __('New Announcement') }}</span>
            </button>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if (session()->has('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-sm text-emerald-600 dark:text-emerald-400"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Filter & Stats Bar -->
    <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-1.5 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0">
            @php
                $typeTabs = [
                    'all' => __('All'),
                    'info' => __('Info'),
                    'warning' => __('Warning'),
                    'critical' => __('Critical'),
                ];
            @endphp

            @foreach ($typeTabs as $val => $label)
                <button
                    type="button"
                    wire:click="$set('filter_type', '{{ $val }}')"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer {{ $filter_type === $val ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-zinc-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="text-xs text-slate-500 font-medium self-end sm:self-auto">
            <span class="font-black text-purple-600 dark:text-purple-400">{{ $activeCount }}</span> {{ __('currently active broadcasts') }}
        </div>
    </div>

    <!-- Announcements Feed / Table -->
    <div class="space-y-4">
        @forelse ($announcements as $ann)
            @php
                $isLive = $ann->is_active && ($ann->starts_at === null || $ann->starts_at->isPast()) && ($ann->ends_at === null || $ann->ends_at->isFuture());
                $typeClasses = match ($ann->type) {
                    'critical' => 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-900/60 text-rose-800 dark:text-rose-200',
                    'warning' => 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-900/60 text-amber-800 dark:text-amber-200',
                    'success' => 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-900/60 text-emerald-800 dark:text-emerald-200',
                    default => 'bg-indigo-50 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-900/60 text-indigo-800 dark:text-indigo-200',
                };
                $badgeClasses = match ($ann->type) {
                    'critical' => 'bg-rose-600 text-white',
                    'warning' => 'bg-amber-600 text-white',
                    'success' => 'bg-emerald-600 text-white',
                    default => 'bg-indigo-600 text-white',
                };
            @endphp
            <div class="p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4 transition">
                <!-- Top Row: Type, Title, Status, Actions -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider {{ $badgeClasses }}">
                            {{ $ann->type }}
                        </span>
                        <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white">
                            {{ $ann->title }}
                        </h3>
                        @if ($isLive)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                {{ __('Live Broadcast') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-slate-400">
                                {{ $ann->is_active ? __('Scheduled / Ended') : __('Inactive') }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            wire:click="toggleActive('{{ $ann->id }}')"
                            class="px-3 py-1 rounded-xl text-xs font-bold transition cursor-pointer {{ $ann->is_active ? 'bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}"
                        >
                            {{ $ann->is_active ? __('Deactivate') : __('Activate') }}
                        </button>

                        <button
                            type="button"
                            wire:click="editAnnouncement('{{ $ann->id }}')"
                            class="p-2 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 text-xs transition cursor-pointer"
                            title="{{ __('Edit Announcement') }}"
                        >
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>

                        <button
                            type="button"
                            wire:click="deleteAnnouncement('{{ $ann->id }}')"
                            wire:confirm="{{ __('Are you sure you want to permanently delete this broadcast?') }}"
                            class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/70 text-rose-600 hover:bg-rose-100 text-xs transition cursor-pointer"
                            title="{{ __('Delete Announcement') }}"
                        >
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>

                <!-- Message Preview Box -->
                <div class="p-4 rounded-2xl border {{ $typeClasses }} text-sm font-medium leading-relaxed">
                    {{ $ann->message }}
                </div>

                <!-- Meta Details Footer -->
                <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 pt-1">
                    <div class="flex items-center gap-4 flex-wrap">
                        <span>
                            <strong class="text-slate-700 dark:text-slate-300 font-semibold">{{ __('Audience:') }}</strong>
                            {{ $ann->targetPlan ? $ann->targetPlan->name . ' tier only' : __('All Operator Tiers') }}
                        </span>
                        <span>
                            <strong class="text-slate-700 dark:text-slate-300 font-semibold">{{ __('Dismissible:') }}</strong>
                            {{ $ann->is_dismissible ? __('Yes') : __('No (Persistent)') }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3 font-mono text-xs">
                        @if ($ann->starts_at || $ann->ends_at)
                            <span>
                                {{ $ann->starts_at?->format('d M Y, H:i') ?? 'Now' }} &rarr; {{ $ann->ends_at?->format('d M Y, H:i') ?? 'Indefinite' }}
                            </span>
                        @else
                            <span>{{ __('Active indefinitely') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="p-12 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 text-center text-slate-400 space-y-2">
                <i class="fa-solid fa-bullhorn text-4xl mb-2 block opacity-30"></i>
                <h3 class="font-bold text-sm text-slate-700 dark:text-slate-300">{{ __('No broadcast notices created') }}</h3>
                <p class="text-xs">{{ __('Click "New Announcement" above to publish a notification to operators.') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Create / Edit Modal -->
    @if ($show_modal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-lg rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-2xl flex flex-col my-8">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-zinc-800 flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-zinc-800/40 rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-purple-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-bullhorn"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    {{ $editing_id ? __('Edit Broadcast Announcement') : __('Create New Broadcast') }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                    {{ __('Display a notification banner across all active operator dashboards.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeModal" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0 -mr-1 -mt-1">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form wire:submit="saveAnnouncement" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div>
                            <x-label for="title" :value="__('Announcement Title')" required />
                            <x-input id="title" type="text" wire:model="title" placeholder="{{ __('e.g. Scheduled System Maintenance') }}" :error="$errors->has('title')" />
                            <x-input-error :messages="$errors->get('title')" />
                        </div>

                        <div>
                            <x-label for="message" :value="__('Broadcast Message')" required />
                            <x-textarea
                                id="message"
                                wire:model="message"
                                rows="3"
                                placeholder="{{ __('Enter the full message details for operators...') }}"
                                :error="$errors->has('message')"
                            />
                            <x-input-error :messages="$errors->get('message')" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="type" :value="__('Notice Severity / Type')" required />
                                <x-select
                                    id="type"
                                    wire:model="type"
                                    :options="[
                                        ['value' => 'info', 'label' => __('Info (Blue)')],
                                        ['value' => 'warning', 'label' => __('Warning (Amber)')],
                                        ['value' => 'critical', 'label' => __('Critical / Urgent (Red)')],
                                        ['value' => 'success', 'label' => __('Success / Feature (Green)')],
                                    ]"
                                    :error="$errors->has('type')"
                                />
                                <x-input-error :messages="$errors->get('type')" />
                            </div>

                            <div>
                                <x-label for="target_plan_id" :value="__('Target Operator Tier')" />
                                @php
                                    $targetPlanOptions = collect([['value' => '', 'label' => __('All Plans & Operators')]])
                                        ->concat($plans->map(fn($p) => ['value' => (string) $p->id, 'label' => $p->name . ' ' . __('only')]))
                                        ->toArray();
                                @endphp
                                <x-select
                                    id="target_plan_id"
                                    wire:model="target_plan_id"
                                    :options="$targetPlanOptions"
                                    :error="$errors->has('target_plan_id')"
                                />
                                <x-input-error :messages="$errors->get('target_plan_id')" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label for="starts_at" :value="__('Start Date & Time')" />
                                <x-input id="starts_at" type="datetime-local" wire:model="starts_at" class="text-xs font-mono" :error="$errors->has('starts_at')" />
                                <x-input-error :messages="$errors->get('starts_at')" />
                            </div>

                            <div>
                                <x-label for="ends_at" :value="__('End Date & Time')" />
                                <x-input id="ends_at" type="datetime-local" wire:model="ends_at" class="text-xs font-mono" :error="$errors->has('ends_at')" />
                                <x-input-error :messages="$errors->get('ends_at')" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-100 dark:border-zinc-800">
                            <div class="p-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 hover:bg-slate-100 dark:hover:bg-zinc-800 transition">
                                <x-checkbox
                                    id="announcement_is_active"
                                    wire:model="is_active"
                                    :label="__('Publish immediately (Active)')"
                                    :description="__('Broadcast will be shown right away')"
                                />
                            </div>

                            <div class="p-3 rounded-2xl border border-slate-200/80 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-800/40 hover:bg-slate-100 dark:hover:bg-zinc-800 transition">
                                <x-checkbox
                                    id="announcement_is_dismissible"
                                    wire:model="is_dismissible"
                                    :label="__('Allow Dismissal')"
                                    :description="__('Operators can dismiss the notice')"
                                />
                            </div>
                        </div>

                        <!-- Modal Actions Footer -->
                        <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-end gap-3">
                            <x-button type="button" variant="secondary" wire:click="closeModal" class="text-xs font-bold">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit" variant="primary" class="text-xs font-bold bg-purple-600 hover:bg-purple-700">
                                <i class="fa-solid fa-bullhorn mr-1.5 text-xs"></i>
                                <span>{{ __('Broadcast Notice') }}</span>
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif
</div>
