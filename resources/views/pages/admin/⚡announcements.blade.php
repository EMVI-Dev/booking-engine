<?php

use App\Models\Plan;
use App\Models\PlatformAnnouncement;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
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

    // Confirmation Modals State
    public bool $showConfirmToggleModal = false;
    public ?string $toggleAnnouncementId = null;

    public bool $showConfirmDeleteModal = false;
    public ?string $deleteAnnouncementId = null;

    #[Computed]
    public function toggleTargetAnnouncement(): ?PlatformAnnouncement
    {
        if (! $this->toggleAnnouncementId) {
            return null;
        }

        return PlatformAnnouncement::find($this->toggleAnnouncementId);
    }

    #[Computed]
    public function deleteTargetAnnouncement(): ?PlatformAnnouncement
    {
        if (! $this->deleteAnnouncementId) {
            return null;
        }

        return PlatformAnnouncement::find($this->deleteAnnouncementId);
    }

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

    public function confirmToggleActive(string $id): void
    {
        $this->toggleAnnouncementId = $id;
        $this->showConfirmToggleModal = true;
    }

    public function closeConfirmToggleModal(): void
    {
        $this->showConfirmToggleModal = false;
        $this->toggleAnnouncementId = null;
    }

    public function executeToggleActive(): void
    {
        if ($this->toggleAnnouncementId) {
            $this->toggleActive($this->toggleAnnouncementId);
        }

        $this->closeConfirmToggleModal();
    }

    public function toggleActive(string $id): void
    {
        $announcement = PlatformAnnouncement::find($id);
        if ($announcement) {
            $announcement->update(['is_active' => ! $announcement->is_active]);
            $status = $announcement->is_active ? __('activated and live') : __('deactivated');
            session()->flash('success', __('Broadcast :title :status.', ['title' => $announcement->title, 'status' => $status]));
        }
    }

    public function confirmDelete(string $id): void
    {
        $this->deleteAnnouncementId = $id;
        $this->showConfirmDeleteModal = true;
    }

    public function closeConfirmDeleteModal(): void
    {
        $this->showConfirmDeleteModal = false;
        $this->deleteAnnouncementId = null;
    }

    public function executeDelete(): void
    {
        if ($this->deleteAnnouncementId) {
            $this->deleteAnnouncement($this->deleteAnnouncementId);
        }

        $this->closeConfirmDeleteModal();
    }

    public function deleteAnnouncement(string $id): void
    {
        PlatformAnnouncement::where('id', $id)->delete();
        session()->flash('success', __('Announcement removed successfully.'));
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
            <span class="p-2.5 rounded-2xl bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30">
                <i class="fa-solid fa-bullhorn text-lg"></i>
            </span>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
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
                class="h-9 px-3.5 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-xs shadow-xs transition flex items-center gap-1.5 cursor-pointer"
            >
                <i class="fa-solid fa-plus text-[10px]"></i>
                <span>{{ __('New Announcement') }}</span>
            </button>
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if (session()->has('success'))
        <div class="p-4 rounded-2xl bg-emerald-950/60 text-emerald-300 border border-emerald-800/60 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-sm text-emerald-400"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Filter & Stats Bar -->
    <div class="p-4 rounded-2xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
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
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 cursor-pointer {{ $filter_type === $val ? 'bg-[#FFEF4D] text-[#090d16] font-black shadow-xs' : 'bg-slate-100 dark:bg-[#141821] text-slate-600 dark:text-zinc-400 hover:bg-slate-200 dark:hover:bg-[#1e2433]' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="text-xs text-slate-500 font-medium self-end sm:self-auto">
            <span class="font-bold text-[#8a7808] dark:text-[#FFEF4D]">{{ $activeCount }}</span> {{ __('currently active broadcasts') }}
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
                    default => 'bg-[#FFEF4D]/5 dark:bg-[#FFEF4D]/5 border-slate-200/80 dark:border-[#1e2433] text-slate-800 dark:text-slate-200',
                };
                $badgeClasses = match ($ann->type) {
                    'critical' => 'bg-rose-950/60 text-rose-400 border border-rose-800/60',
                    'warning' => 'bg-amber-950/60 text-amber-400 border border-amber-800/60',
                    'success' => 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/60',
                    default => 'bg-[#FFEF4D]/10 text-[#8a7808] dark:text-[#FFEF4D] border border-[#FFEF4D]/30',
                };
            @endphp
            <div class="p-6 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-xs space-y-4 transition">
                <!-- Top Row: Type, Title, Status, Actions -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-[#1e2433]">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider {{ $badgeClasses }}">
                            {{ $ann->type }}
                        </span>
                        <h3 class="font-bold text-base sm:text-lg text-slate-900 dark:text-white">
                            {{ $ann->title }}
                        </h3>
                        @if ($isLive)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-950/60 text-emerald-400 border border-emerald-800/60">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                {{ __('Live Broadcast') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 dark:bg-[#141821] dark:text-slate-400 border border-slate-200 dark:border-[#1e2433]">
                                {{ $ann->is_active ? __('Scheduled / Ended') : __('Inactive') }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            wire:click="confirmToggleActive('{{ $ann->id }}')"
                            class="h-8 px-3 rounded-xl text-xs font-bold transition cursor-pointer {{ $ann->is_active ? 'bg-slate-100 dark:bg-[#141821] text-slate-700 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-[#1e2433] border border-slate-200 dark:border-[#1e2433]' : 'bg-emerald-600 text-white hover:bg-emerald-700' }}"
                        >
                            {{ $ann->is_active ? __('Deactivate') : __('Activate') }}
                        </button>

                        <button
                            type="button"
                            wire:click="editAnnouncement('{{ $ann->id }}')"
                            class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-[#141821] text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-[#1e2433] border border-slate-200 dark:border-[#1e2433] text-xs transition cursor-pointer inline-flex items-center justify-center shadow-2xs"
                            title="{{ __('Edit Announcement') }}"
                        >
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                        </button>

                        <button
                            type="button"
                            wire:click="confirmDelete('{{ $ann->id }}')"
                            class="h-8 w-8 rounded-xl bg-rose-50 dark:bg-rose-950/70 text-rose-600 dark:text-rose-400 hover:bg-rose-100 border border-rose-200 dark:border-rose-900/60 text-xs transition cursor-pointer inline-flex items-center justify-center shadow-2xs"
                            title="{{ __('Delete Announcement') }}"
                        >
                            <i class="fa-solid fa-trash-can text-xs"></i>
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
            <div class="p-12 rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] text-center text-slate-400 space-y-2">
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
                <div class="w-full max-w-lg rounded-3xl bg-white dark:bg-[#0C0E13] border border-slate-200/80 dark:border-[#1e2433] shadow-2xl flex flex-col my-8">
                    <!-- Modal Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-[#1e2433] flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-[#10141d] rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-[#FFEF4D] text-[#090d16] font-black flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-bullhorn"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-bold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
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
                    <form wire:submit="saveAnnouncement" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto pb-36">
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
                                        ['value' => 'info', 'label' => __('Info (Standard)')],
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
                                <x-datetime-picker id="starts_at" wire:model="starts_at" :error="$errors->has('starts_at')" />
                                <x-input-error :messages="$errors->get('starts_at')" />
                            </div>

                            <div>
                                <x-label for="ends_at" :value="__('End Date & Time')" />
                                <x-datetime-picker id="ends_at" wire:model="ends_at" :error="$errors->has('ends_at')" />
                                <x-input-error :messages="$errors->get('ends_at')" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-100 dark:border-[#1e2433]">
                            <div class="p-3 rounded-2xl border border-slate-200/80 dark:border-[#1e2433] bg-slate-50/50 dark:bg-[#141821]/40 hover:bg-slate-100 dark:hover:bg-[#141821] transition">
                                <x-checkbox
                                    id="announcement_is_active"
                                    wire:model="is_active"
                                    :label="__('Publish immediately (Active)')"
                                    :description="__('Broadcast will be shown right away')"
                                />
                            </div>

                            <div class="p-3 rounded-2xl border border-slate-200/80 dark:border-[#1e2433] bg-slate-50/50 dark:bg-[#141821]/40 hover:bg-slate-100 dark:hover:bg-[#141821] transition">
                                <x-checkbox
                                    id="announcement_is_dismissible"
                                    wire:model="is_dismissible"
                                    :label="__('Allow Dismissal')"
                                    :description="__('Operators can dismiss the notice')"
                                />
                            </div>
                        </div>

                        <!-- Modal Actions Footer -->
                        <div class="pt-4 border-t border-slate-100 dark:border-[#1e2433] flex items-center justify-end gap-3">
                            <button type="button" wire:click="closeModal" class="h-9 px-4 rounded-xl bg-slate-100 dark:bg-[#141821] hover:bg-slate-200 dark:hover:bg-[#1e2433] text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-[#1e2433] text-xs font-bold transition cursor-pointer">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="h-9 px-4 rounded-xl bg-[#FFEF4D] hover:bg-[#fae639] text-[#090d16] font-black text-xs shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-bullhorn text-xs"></i>
                                <span>{{ __('Broadcast Notice') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Toggle Status Confirmation Modal -->
    @if ($showConfirmToggleModal && $this->toggleTargetAnnouncement)
        @php
            $targetAnn = $this->toggleTargetAnnouncement;
            $willBeActive = ! $targetAnn->is_active;
        @endphp
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto" wire:keydown.escape="closeConfirmToggleModal">
                <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#0C0E13] shadow-2xl border border-slate-200/80 dark:border-[#1e2433] flex flex-col my-8" @click.outside="$wire.closeConfirmToggleModal()">
                    <!-- Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-[#1e2433] flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-[#10141d] rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl {{ $willBeActive ? 'bg-emerald-600' : 'bg-amber-600' }} text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid {{ $willBeActive ? 'fa-circle-check' : 'fa-pause' }}"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-bold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    {{ $willBeActive ? __('Activate Broadcast Notice') : __('Deactivate Broadcast Notice') }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                    {{ $targetAnn->title }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeConfirmToggleModal" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-6 space-y-3 text-xs text-slate-600 dark:text-slate-300">
                        @if ($willBeActive)
                            <p class="leading-relaxed">
                                {{ __('Are you sure you want to activate') }} <strong>"{{ $targetAnn->title }}"</strong>?
                            </p>
                            <div class="p-3.5 rounded-2xl bg-emerald-950/40 border border-emerald-900/50 space-y-1 text-emerald-200">
                                <div class="font-bold flex items-center gap-1.5">
                                    <i class="fa-solid fa-bullhorn text-xs"></i>
                                    <span>{{ __('Live Audience Broadcast') }}</span>
                                </div>
                                <p class="text-[11px] text-emerald-300 leading-normal">
                                    {{ __('This notification banner will immediately be displayed across operator portals according to its audience settings.') }}
                                </p>
                            </div>
                        @else
                            <p class="leading-relaxed">
                                {{ __('Are you sure you want to deactivate') }} <strong>"{{ $targetAnn->title }}"</strong>?
                            </p>
                            <div class="p-3.5 rounded-2xl bg-amber-950/40 border border-amber-900/50 space-y-1 text-amber-200">
                                <div class="font-bold flex items-center gap-1.5">
                                    <i class="fa-solid fa-eye-slash text-xs"></i>
                                    <span>{{ __('Hide From Operators') }}</span>
                                </div>
                                <p class="text-[11px] text-amber-300 leading-normal">
                                    {{ __('This notice will be hidden from all operator dashboards immediately.') }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <!-- Footer -->
                    <div class="p-4 border-t border-slate-100 dark:border-[#1e2433] flex items-center justify-end gap-2.5 bg-slate-50/50 dark:bg-[#10141d] rounded-b-3xl">
                        <button type="button" wire:click="closeConfirmToggleModal" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#141821] transition cursor-pointer">
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            wire:click="executeToggleActive"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-white transition cursor-pointer shadow-xs {{ $willBeActive ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-amber-600 hover:bg-amber-700' }}"
                        >
                            {{ $willBeActive ? __('Confirm & Activate') : __('Confirm & Deactivate') }}
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif

    <!-- Delete Confirmation Modal -->
    @if ($showConfirmDeleteModal && $this->deleteTargetAnnouncement)
        @php
            $deleteAnn = $this->deleteTargetAnnouncement;
        @endphp
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto" wire:keydown.escape="closeConfirmDeleteModal">
                <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#0C0E13] shadow-2xl border border-slate-200/80 dark:border-[#1e2433] flex flex-col my-8" @click.outside="$wire.closeConfirmDeleteModal()">
                    <!-- Header -->
                    <div class="p-6 border-b border-slate-100 dark:border-[#1e2433] flex items-start justify-between gap-4 bg-slate-50/50 dark:bg-[#10141d] rounded-t-3xl">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-rose-600 text-white flex items-center justify-center text-base shadow-xs shrink-0 mt-0.5">
                                <i class="fa-solid fa-trash-can"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-bold text-base sm:text-lg text-slate-900 dark:text-white leading-tight truncate">
                                    {{ __('Delete Broadcast Notice') }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                    {{ $deleteAnn->title }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeConfirmDeleteModal" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-6 space-y-3 text-xs text-slate-600 dark:text-slate-300">
                        <p class="leading-relaxed">
                            {{ __('Are you sure you want to permanently delete') }} <strong>"{{ $deleteAnn->title }}"</strong>?
                        </p>
                        <div class="p-3.5 rounded-2xl bg-rose-950/40 border border-rose-900/50 space-y-1 text-rose-200">
                            <div class="font-bold flex items-center gap-1.5">
                                <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                                <span>{{ __('Permanent Action') }}</span>
                            </div>
                            <p class="text-[11px] text-rose-300 leading-normal">
                                {{ __('This broadcast record and all operator dismissal history will be permanently deleted.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-4 border-t border-slate-100 dark:border-[#1e2433] flex items-center justify-end gap-2.5 bg-slate-50/50 dark:bg-[#10141d] rounded-b-3xl">
                        <button type="button" wire:click="closeConfirmDeleteModal" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#141821] transition cursor-pointer">
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            wire:click="executeDelete"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 transition cursor-pointer shadow-xs"
                        >
                            {{ __('Delete Announcement') }}
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
