<?php

use App\Models\AdminAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Audit log')] #[Layout('layouts.admin')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return AdminAuditLog::query()
            ->with(['operator:id,name'])
            ->when(filled($this->search), function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('action', 'like', $term)
                        ->orWhere('actor_email', 'like', $term)
                        ->orWhereHas('operator', fn ($operatorQuery) => $operatorQuery->where('name', 'like', $term));
                });
            })
            ->latest('created_at')
            ->paginate(30);
    }
}; ?>

<div class="space-y-6 w-full">
    {{-- Header --}}
    <x-page-header
        :title="__('Audit log')"
        :subtitle="__('Every action taken by platform administrators.')"
        icon="fa-clipboard-list"
        class="pb-2 border-b border-[#E4E5E9] dark:border-[#1E2433]"
    />

    {{-- Search Toolbar --}}
    <div class="flex items-center">
        <div class="relative w-full sm:w-80">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[#60646C] dark:text-zinc-400"></i>
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="{{ __('Search action, admin email or operator') }}"
                class="w-full pl-8 pr-3 py-1.5 text-xs rounded-[6px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] text-[#1C2024] dark:text-white placeholder-[#60646C]/60 dark:placeholder-zinc-500 focus:outline-hidden focus:border-[#FFEF4D] shadow-none"
            />
        </div>
    </div>

    {{-- Audit Log Table Card --}}
    <div class="overflow-x-auto rounded-[12px] border border-[#E4E5E9] dark:border-[#1E2433] bg-white dark:bg-[#10141d] shadow-none">
        <table class="w-full text-left text-xs">
            <thead class="bg-[#FAFAFB] dark:bg-[#141821] border-b border-[#E4E5E9] dark:border-[#1E2433] text-[11px] font-medium text-[#60646C] dark:text-zinc-400 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3">{{ __('When') }}</th>
                    <th class="px-4 py-3">{{ __('Admin') }}</th>
                    <th class="px-4 py-3">{{ __('Action') }}</th>
                    <th class="px-4 py-3">{{ __('Operator') }}</th>
                    <th class="px-4 py-3">{{ __('Details') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#E4E5E9] dark:divide-[#1E2433]">
                @forelse ($this->entries as $entry)
                    <tr wire:key="audit-{{ $entry->id }}" class="hover:bg-[#F4F5F6]/60 dark:hover:bg-[#141821]/60 transition">
                        <td class="px-4 py-3 whitespace-nowrap text-[#60646C] dark:text-zinc-400 font-mono text-[11px]">
                            {{ $entry->created_at->format('d M Y H:i') }}
                        </td>
                        <td class="px-4 py-3 font-medium text-[#1C2024] dark:text-white">
                            {{ $entry->actor_email ?? __('System') }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-1.5 py-0.5 rounded-[4px] bg-[#EFEFF0] dark:bg-[#1A202C] text-[#1C2024] dark:text-zinc-200 font-mono text-[11px] border border-[#E4E5E9] dark:border-[#2D3748]">
                                {{ $entry->action }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-[#1C2024] dark:text-zinc-300">
                            {{ $entry->operator?->name ?? '-' }}
                        </td>
                        <td class="px-4 py-3 font-mono text-[11px] text-[#60646C] dark:text-zinc-400 break-all">
                            {{ $entry->context ? json_encode($entry->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-xs text-[#60646C] dark:text-zinc-400">
                            {{ __('No admin actions recorded yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($this->entries->hasPages())
        <div class="pt-2">
            {{ $this->entries->links() }}
        </div>
    @endif
</div>
