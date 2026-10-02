<?php

use App\Concerns\RecordsAdminActions;
use App\Models\Plan;
use App\Models\PlatformAnnouncement;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notices')] #[Layout('layouts.admin')] class extends Component {
    use RecordsAdminActions;

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
            $this->audit('announcement.updated', PlatformAnnouncement::find($this->editing_id), ['title' => $this->title]);
            session()->flash('success', __('Announcement updated successfully!'));
        } else {
            $this->audit('announcement.created', PlatformAnnouncement::create($attributes), ['title' => $this->title]);
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
            $this->audit('announcement.toggled', $announcement, ['is_active' => $announcement->is_active]);
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
        $this->audit('announcement.deleted', null, ['announcement_id' => $id, 'title' => PlatformAnnouncement::find($id)?->title]);
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
    <x-page-header
        :title="__('Notices')"
        :subtitle="__('Short messages that show on operator dashboards.')"
        icon="fa-bullhorn"
    >
        <x-slot:actions>
            <button type="button" wire:click="openCreateModal"
                class="h-8 px-3 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-medium text-[13px] inline-flex items-center gap-1.5 shadow-none transition cursor-pointer">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>{{ __('New notice') }}</span>
            </button>
        </x-slot:actions>
    </x-page-header>

    <!-- Feedback Alerts -->
    @if (session()->has('success'))
        <div class="p-3.5 rounded-[12px] bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0] text-[13px] font-medium flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-sm text-[#059669]"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <x-toolbar class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-filter-tabs>
            <x-filter-tab wire:click="$set('filter_type', 'all')" :active="$filter_type === 'all'">{{ __('All') }}</x-filter-tab>
            <x-filter-tab wire:click="$set('filter_type', 'info')" :active="$filter_type === 'info'">{{ __('Info') }}</x-filter-tab>
            <x-filter-tab wire:click="$set('filter_type', 'warning')" :active="$filter_type === 'warning'">{{ __('Warning') }}</x-filter-tab>
            <x-filter-tab wire:click="$set('filter_type', 'critical')" :active="$filter_type === 'critical'">{{ __('Critical') }}</x-filter-tab>
        </x-filter-tabs>

        <p class="text-[12px] font-medium text-[#60646C]">
            <span class="font-semibold text-[#1C2024] dark:text-white">{{ $activeCount }}</span> {{ __('currently active broadcasts') }}
        </p>
    </x-toolbar>

    <!-- Announcements Feed -->
    <div class="space-y-4">
        @forelse ($announcements as $ann)
            @php
                $isLive = $ann->is_active && ($ann->starts_at === null || $ann->starts_at->isPast()) && ($ann->ends_at === null || $ann->ends_at->isFuture());
                $typeClasses = match ($ann->type) {
                    'critical' => 'bg-[#FEF2F2] border-[#FECACA] text-[#991B1B]',
                    'warning' => 'bg-[#FFFBEB] border-[#FDE68A] text-[#92400E]',
                    'success' => 'bg-[#ECFDF5] border-[#A7F3D0] text-[#065F46]',
                    default => 'bg-[#FAFAFB] dark:bg-[#141821] border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-slate-200',
                };
                $badgeClasses = match ($ann->type) {
                    'critical' => 'bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA]',
                    'warning' => 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]',
                    'success' => 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]',
                    default => 'bg-[#FFEF4D]/20 text-[#856404] dark:text-[#FFEF4D] border border-[#FFEF4D]/40',
                };
            @endphp
            <div class="p-5 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none space-y-3.5 transition">
                <!-- Top Row: Type, Title, Status, Actions -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#E4E5E9] dark:border-[#1E2433]">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="px-2 py-0.5 rounded-[6px] text-[11px] font-medium uppercase tracking-wider {{ $badgeClasses }}">
                            {{ $ann->type }}
                        </span>
                        <h3 class="font-semibold text-[15px] text-[#1C2024] dark:text-white">
                            {{ $ann->title }}
                        </h3>
                        @if ($isLive)
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#059669]"></span>
                                {{ __('Live Broadcast') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-[6px] text-[11px] font-medium bg-[#FAFAFB] text-[#60646C] dark:bg-[#141821] dark:text-slate-400 border border-[#E4E5E9] dark:border-[#1E2433]">
                                {{ $ann->is_active ? __('Scheduled / Ended') : __('Inactive') }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            wire:click="confirmToggleActive('{{ $ann->id }}')"
                            class="h-7 px-2.5 rounded-[6px] text-[11px] font-medium transition cursor-pointer shadow-none {{ $ann->is_active ? 'bg-white dark:bg-[#141821] text-[#1C2024] dark:text-slate-300 hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433]' : 'bg-[#ECFDF5] text-[#065F46] hover:bg-[#D1FAE5] border border-[#A7F3D0]' }}"
                        >
                            {{ $ann->is_active ? __('Deactivate') : __('Activate') }}
                        </button>

                        <button
                            type="button"
                            wire:click="editAnnouncement('{{ $ann->id }}')"
                            class="h-7 w-7 rounded-[6px] bg-white dark:bg-[#141821] text-[#60646C] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[11px] transition cursor-pointer inline-flex items-center justify-center shadow-none"
                            title="{{ __('Edit Announcement') }}"
                        >
                            <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                        </button>

                        <button
                            type="button"
                            wire:click="confirmDelete('{{ $ann->id }}')"
                            class="h-7 w-7 rounded-[6px] bg-[#FEF2F2] text-[#991B1B] hover:bg-[#FEE2E2] border border-[#FECACA] text-[11px] transition cursor-pointer inline-flex items-center justify-center shadow-none"
                            title="{{ __('Delete Announcement') }}"
                        >
                            <i class="fa-solid fa-trash-can text-[10px]"></i>
                        </button>
                    </div>
                </div>

                <!-- Message Preview Box -->
                <div class="p-3.5 rounded-[8px] border {{ $typeClasses }} text-[13px] leading-relaxed">
                    {{ $ann->message }}
                </div>

                <!-- Meta Details Footer -->
                <div class="flex flex-wrap items-center justify-between gap-3 text-[12px] text-[#60646C] dark:text-slate-400 pt-1">
                    <div class="flex items-center gap-4 flex-wrap">
                        <span>
                            <strong class="text-[#1C2024] dark:text-slate-300 font-medium">{{ __('Audience:') }}</strong>
                            {{ $ann->targetPlan ? $ann->targetPlan->name . ' tier only' : __('All Operator Tiers') }}
                        </span>
                        <span>
                            <strong class="text-[#1C2024] dark:text-slate-300 font-medium">{{ __('Dismissible:') }}</strong>
                            {{ $ann->is_dismissible ? __('Yes') : __('No (Persistent)') }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3 font-mono text-[11px]">
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
            <div class="p-10 rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] text-center text-[#8B8D98] space-y-2">
                <i class="fa-solid fa-bullhorn text-3xl mb-2 block opacity-30"></i>
                <h3 class="font-medium text-[13px] text-[#60646C] dark:text-slate-300">{{ __('No broadcast notices created') }}</h3>
                <p class="text-[12px]">{{ __('Click "New notice" above to publish a notification to operators.') }}</p>
            </div>
        @endforelse
    </div>

    <!-- Create / Edit Modal -->
    @if ($show_modal)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-[#12181E]/60 backdrop-blur-xs overflow-y-auto">
                <div class="w-full max-w-lg rounded-[12px] bg-white dark:bg-[#10141d] border border-[#E4E5E9] dark:border-[#1E2433] shadow-none flex flex-col my-8 overflow-hidden">
                    <!-- Modal Header -->
                    <div class="p-4 sm:p-5 border-b border-[#E4E5E9] dark:border-[#1E2433] flex items-start justify-between gap-3 bg-[#FAFAFB] dark:bg-[#141821]">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-[8px] bg-[#FFEF4D] text-[#12181E] flex items-center justify-center text-sm shadow-none shrink-0 mt-0.5 font-semibold">
                                <i class="fa-solid fa-bullhorn"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-semibold text-[16px] text-[#1C2024] dark:text-white leading-tight truncate">
                                    {{ $editing_id ? __('Edit Broadcast Announcement') : __('Create New Broadcast') }}
                                </h3>
                                <p class="text-[12px] text-[#60646C] dark:text-slate-400">
                                    {{ __('Display a notification banner across all active operator dashboards.') }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeModal" class="p-1.5 rounded-[6px] text-[#8B8D98] hover:text-[#1C2024] dark:hover:text-white transition cursor-pointer shrink-0">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form wire:submit="saveAnnouncement" class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
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

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-[#E4E5E9] dark:border-[#1E2433]">
                            <div class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                <x-checkbox
                                    id="announcement_is_active"
                                    wire:model="is_active"
                                    :label="__('Publish immediately (Active)')"
                                    :description="__('Broadcast will be shown right away')"
                                />
                            </div>

                            <div class="p-2.5 rounded-[8px] border border-[#E4E5E9] dark:border-[#1E2433] bg-[#FAFAFB] dark:bg-[#141821]/40 transition">
                                <x-checkbox
                                    id="announcement_is_dismissible"
                                    wire:model="is_dismissible"
                                    :label="__('Allow Dismissal')"
                                    :description="__('Operators can dismiss the notice')"
                                />
                            </div>
                        </div>

                        <!-- Modal Actions Footer -->
                        <div class="pt-4 border-t border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-end gap-2.5">
                            <button type="button" wire:click="closeModal" class="h-8 px-3.5 rounded-[6px] bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] text-[#1C2024] dark:text-slate-200 text-[13px] font-medium transition cursor-pointer shadow-none">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="h-8 px-3.5 rounded-[6px] bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] font-medium text-[13px] shadow-none transition inline-flex items-center gap-1.5 cursor-pointer">
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
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-[#12181E]/60 backdrop-blur-xs overflow-y-auto" wire:keydown.escape="closeConfirmToggleModal">
                <div class="w-full max-w-md rounded-[12px] bg-white dark:bg-[#10141d] shadow-none border border-[#E4E5E9] dark:border-[#1E2433] flex flex-col my-8 overflow-hidden" @click.outside="$wire.closeConfirmToggleModal()">
                    <!-- Header -->
                    <div class="p-4 sm:p-5 border-b border-[#E4E5E9] dark:border-[#1E2433] flex items-start justify-between gap-3 bg-[#FAFAFB] dark:bg-[#141821]">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-[8px] {{ $willBeActive ? 'bg-[#ECFDF5] text-[#065F46] border border-[#A7F3D0]' : 'bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A]' }} flex items-center justify-center text-sm shadow-none shrink-0 mt-0.5">
                                <i class="fa-solid {{ $willBeActive ? 'fa-circle-check' : 'fa-pause' }}"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-semibold text-[16px] text-[#1C2024] dark:text-white leading-tight truncate">
                                    {{ $willBeActive ? __('Activate Broadcast Notice') : __('Deactivate Broadcast Notice') }}
                                </h3>
                                <p class="text-[12px] text-[#60646C] dark:text-slate-400 truncate">
                                    {{ $targetAnn->title }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeConfirmToggleModal" class="p-1.5 rounded-[6px] text-[#8B8D98] hover:text-[#1C2024] dark:hover:text-white transition cursor-pointer">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-5 space-y-3 text-[13px] text-[#60646C] dark:text-slate-300">
                        @if ($willBeActive)
                            <p class="leading-relaxed">
                                {{ __('Are you sure you want to activate') }} <strong>"{{ $targetAnn->title }}"</strong>?
                            </p>
                            <div class="p-3 rounded-[8px] bg-[#ECFDF5] border border-[#A7F3D0] space-y-1 text-[#065F46]">
                                <div class="font-medium flex items-center gap-1.5 text-[12px]">
                                    <i class="fa-solid fa-bullhorn text-xs"></i>
                                    <span>{{ __('Live Audience Broadcast') }}</span>
                                </div>
                                <p class="text-[12px] leading-normal">
                                    {{ __('This notification banner will immediately be displayed across operator portals according to its audience settings.') }}
                                </p>
                            </div>
                        @else
                            <p class="leading-relaxed">
                                {{ __('Are you sure you want to deactivate') }} <strong>"{{ $targetAnn->title }}"</strong>?
                            </p>
                            <div class="p-3 rounded-[8px] bg-[#FFFBEB] border border-[#FDE68A] space-y-1 text-[#92400E]">
                                <div class="font-medium flex items-center gap-1.5 text-[12px]">
                                    <i class="fa-solid fa-eye-slash text-xs"></i>
                                    <span>{{ __('Hide From Operators') }}</span>
                                </div>
                                <p class="text-[12px] leading-normal">
                                    {{ __('This notice will be hidden from all operator dashboards immediately.') }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <!-- Footer -->
                    <div class="p-4 border-t border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-end gap-2.5 bg-[#FAFAFB] dark:bg-[#141821]">
                        <button type="button" wire:click="closeConfirmToggleModal" class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium text-[#1C2024] dark:text-slate-300 bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] transition cursor-pointer shadow-none">
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            wire:click="executeToggleActive"
                            class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium transition cursor-pointer shadow-none {{ $willBeActive ? 'bg-[#ECFDF5] hover:bg-[#D1FAE5] text-[#065F46] border border-[#A7F3D0]' : 'bg-[#FFFBEB] hover:bg-[#FEF3C7] text-[#92400E] border border-[#FDE68A]' }}"
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
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-[#12181E]/60 backdrop-blur-xs overflow-y-auto" wire:keydown.escape="closeConfirmDeleteModal">
                <div class="w-full max-w-md rounded-[12px] bg-white dark:bg-[#10141d] shadow-none border border-[#E4E5E9] dark:border-[#1E2433] flex flex-col my-8 overflow-hidden" @click.outside="$wire.closeConfirmDeleteModal()">
                    <!-- Header -->
                    <div class="p-4 sm:p-5 border-b border-[#E4E5E9] dark:border-[#1E2433] flex items-start justify-between gap-3 bg-[#FAFAFB] dark:bg-[#141821]">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-[8px] bg-[#FEF2F2] text-[#991B1B] border border-[#FECACA] flex items-center justify-center text-sm shadow-none shrink-0 mt-0.5">
                                <i class="fa-solid fa-trash-can"></i>
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h3 class="font-semibold text-[16px] text-[#1C2024] dark:text-white leading-tight truncate">
                                    {{ __('Delete Broadcast Notice') }}
                                </h3>
                                <p class="text-[12px] text-[#60646C] dark:text-slate-400 truncate">
                                    {{ $deleteAnn->title }}
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeConfirmDeleteModal" class="p-1.5 rounded-[6px] text-[#8B8D98] hover:text-[#1C2024] dark:hover:text-white transition cursor-pointer">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-5 space-y-3 text-[13px] text-[#60646C] dark:text-slate-300">
                        <p class="leading-relaxed">
                            {{ __('Are you sure you want to permanently delete') }} <strong>"{{ $deleteAnn->title }}"</strong>?
                        </p>
                        <div class="p-3 rounded-[8px] bg-[#FEF2F2] border border-[#FECACA] space-y-1 text-[#991B1B]">
                            <div class="font-medium flex items-center gap-1.5 text-[12px]">
                                <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                                <span>{{ __('Permanent Action') }}</span>
                            </div>
                            <p class="text-[12px] leading-normal">
                                {{ __('This broadcast record and all operator dismissal history will be permanently deleted.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-4 border-t border-[#E4E5E9] dark:border-[#1E2433] flex items-center justify-end gap-2.5 bg-[#FAFAFB] dark:bg-[#141821]">
                        <button type="button" wire:click="closeConfirmDeleteModal" class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium text-[#1C2024] dark:text-slate-300 bg-white dark:bg-[#141821] hover:bg-[#FAFAFB] dark:hover:bg-[#1E2433] border border-[#E4E5E9] dark:border-[#1E2433] transition cursor-pointer shadow-none">
                            {{ __('Cancel') }}
                        </button>
                        <button
                            type="button"
                            wire:click="executeDelete"
                            class="h-8 px-3.5 rounded-[6px] text-[13px] font-medium text-[#991B1B] bg-[#FEF2F2] hover:bg-[#FEE2E2] border border-[#FECACA] transition cursor-pointer shadow-none"
                        >
                            {{ __('Delete Announcement') }}
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
