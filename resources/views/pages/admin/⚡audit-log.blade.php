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
    <x-page-header
        :title="__('Audit log')"
        :subtitle="__('Every action taken by platform administrators.')"
        icon="fa-clipboard-list"
    />

    <x-search-input wire:model.live.debounce.300ms="search" :placeholder="__('Search action, admin email or operator')" />

    <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-[#1e2433] bg-white dark:bg-[#0C0E13]">
        <table class="w-full text-left text-xs">
            <thead class="text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3">{{ __('When') }}</th>
                    <th class="px-4 py-3">{{ __('Admin') }}</th>
                    <th class="px-4 py-3">{{ __('Action') }}</th>
                    <th class="px-4 py-3">{{ __('Operator') }}</th>
                    <th class="px-4 py-3">{{ __('Details') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#1e2433]">
                @forelse ($this->entries as $entry)
                    <tr wire:key="audit-{{ $entry->id }}">
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $entry->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $entry->actor_email ?? __('System') }}</td>
                        <td class="px-4 py-3 font-mono">{{ $entry->action }}</td>
                        <td class="px-4 py-3">{{ $entry->operator?->name ?? '-' }}</td>
                        <td class="px-4 py-3 font-mono text-[11px] text-slate-500 break-all">{{ $entry->context ? json_encode($entry->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">{{ __('No admin actions recorded yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->entries->links() }}
</div>
