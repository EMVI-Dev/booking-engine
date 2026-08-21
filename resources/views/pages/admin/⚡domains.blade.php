<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\OperatorDomain;
use App\Services\DomainResolverService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Custom Domains & DNS Manager')] #[Layout('layouts.admin')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $type_filter = 'all';
    public string $status_filter = 'all';

    // DNS Inspector state
    public ?string $inspecting_domain_id = null;
    public ?array $dns_check_result = null;
    public bool $is_checking_dns = false;

    /**
     * Reset pagination when filters change.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Perform live DNS lookup verification for a domain.
     */
    public function checkDns(string $domainId): void
    {
        $this->inspecting_domain_id = $domainId;
        $this->is_checking_dns = true;
        $this->dns_check_result = null;

        $domainRecord = OperatorDomain::with('operator')->find($domainId);

        if (! $domainRecord) {
            $this->is_checking_dns = false;
            return;
        }

        $domain = $domainRecord->domain;
        $platformDomain = app(DomainResolverService::class)->getPlatformDomain();

        // Perform DNS lookup
        $cnameRecords = @dns_get_record($domain, DNS_CNAME) ?: [];
        $aRecords = @dns_get_record($domain, DNS_A) ?: [];

        $cnameTargets = array_column($cnameRecords, 'target');
        $aIps = array_column($aRecords, 'ip');

        $cnameMatch = false;
        foreach ($cnameTargets as $target) {
            if (str_contains(strtolower($target), strtolower($platformDomain))) {
                $cnameMatch = true;
                break;
            }
        }

        $isValid = $cnameMatch || ! empty($aIps) || str_ends_with($domain, '.'.$platformDomain) || in_array($domain, ['localhost', '127.0.0.1'], true);

        $this->dns_check_result = [
            'domain' => $domain,
            'platform_domain' => $platformDomain,
            'cname_records' => $cnameTargets,
            'a_records' => $aIps,
            'is_valid' => $isValid,
            'checked_at' => now()->toDateTimeString(),
        ];

        $this->is_checking_dns = false;
    }

    /**
     * Activate domain and mark SSL issued.
     */
    public function activateDomain(string $domainId): void
    {
        $domainRecord = OperatorDomain::find($domainId);

        if ($domainRecord) {
            $domainRecord->update([
                'status' => DomainStatus::Active,
                'verified_at' => now(),
                'ssl_issued_at' => now(),
            ]);

            session()->flash('status', __('Custom domain :domain has been successfully activated and verified.', ['domain' => $domainRecord->domain]));
        }
    }

    /**
     * Update a domain's status directly.
     */
    public function setDomainStatus(string $domainId, string $status): void
    {
        $domainRecord = OperatorDomain::find($domainId);

        if ($domainRecord && in_array($status, [DomainStatus::Active->value, DomainStatus::Pending->value, DomainStatus::Failed->value])) {
            $domainRecord->update(['status' => $status]);
            session()->flash('status', __('Domain :domain status updated to :status.', ['domain' => $domainRecord->domain, 'status' => $status]));
        }
    }

    /**
     * Render domains management component.
     */
    public function render()
    {
        $platformDomain = app(DomainResolverService::class)->getPlatformDomain();

        $query = OperatorDomain::with(['operator']);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('domain', 'like', '%' . $this->search . '%')
                    ->orWhereHas('operator', function ($agentQ) {
                        $agentQ->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('slug', 'like', '%' . $this->search . '%');
                    });
            });
        }

        if ($this->type_filter !== 'all') {
            $query->where('type', $this->type_filter);
        }

        if ($this->status_filter !== 'all') {
            $query->where('status', $this->status_filter);
        }

        $domains = $query->latest()->paginate(15);

        $totalDomains = OperatorDomain::count();
        $customDomainsCount = OperatorDomain::where('type', DomainType::Custom)->count();
        $activeCustomCount = OperatorDomain::where('type', DomainType::Custom)->where('status', DomainStatus::Active)->count();
        $pendingDnsCount = OperatorDomain::where('status', DomainStatus::Pending)->count();

        return view('pages.admin.⚡domains', [
            'domains' => $domains,
            'platformDomain' => $platformDomain,
            'totalDomains' => $totalDomains,
            'customDomainsCount' => $customDomainsCount,
            'activeCustomCount' => $activeCustomCount,
            'pendingDnsCount' => $pendingDnsCount,
        ]);
    }
}; ?>

<div class="space-y-6">
    <!-- Page Header & Metrics Summary -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                {{ __('Custom Domains & DNS Manager') }}
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Review, verify live DNS routing, and manage SSL certificates for operator custom domains.') }}
            </p>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Total Domains') }}</span>
            <p class="text-xl font-black text-slate-900 dark:text-white mt-1">{{ $totalDomains }}</p>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">{{ __('Custom Domains') }}</span>
            <p class="text-xl font-black text-purple-600 dark:text-purple-400 mt-1">{{ $customDomainsCount }}</p>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __('Active SSL Issued') }}</span>
            <p class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ $activeCustomCount }}</p>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">{{ __('Pending DNS Check') }}</span>
            <p class="text-xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ $pendingDnsCount }}</p>
        </div>
    </div>

    <!-- Feedback Flash Alerts -->
    @if (session()->has('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-sm text-emerald-600 dark:text-emerald-400"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session()->has('info'))
        <div class="p-4 rounded-2xl bg-indigo-50 text-indigo-800 dark:bg-indigo-950/70 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-info text-sm text-indigo-600 dark:text-indigo-400"></i>
            <span>{{ session('info') }}</span>
        </div>
    @endif

    <!-- DNS Diagnostic Tool Card (When Inspecting) -->
    @if ($dns_check_result)
        <div class="p-5 rounded-3xl bg-slate-900 text-white border border-slate-800 shadow-xl space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-400 flex items-center gap-2 uppercase tracking-wider">
                    <i class="fa-solid fa-network-wired text-purple-400"></i>
                    {{ __('Live DNS Resolver Result: :domain', ['domain' => $dns_check_result['domain']]) }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $dns_check_result['is_valid'] ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30' }}">
                    {{ $dns_check_result['is_valid'] ? __('DNS Pointed Correctly ✓') : __('DNS Record Not Found / Unresolved') }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-slate-800/60 p-4 rounded-2xl border border-slate-700/60">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase">{{ __('CNAME Records Found') }}</span>
                    <p class="font-mono text-slate-200 mt-0.5">
                        {{ !empty($dns_check_result['cname_records']) ? implode(', ', $dns_check_result['cname_records']) : __('None detected') }}
                    </p>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase">{{ __('A (IP) Records Found') }}</span>
                    <p class="font-mono text-slate-200 mt-0.5">
                        {{ !empty($dns_check_result['a_records']) ? implode(', ', $dns_check_result['a_records']) : __('None detected') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <span class="text-[11px] text-slate-400 font-mono">
                    {{ __('Checked at :time', ['time' => $dns_check_result['checked_at']]) }}
                </span>
                @if ($dns_check_result['is_valid'] && $inspecting_domain_id)
                    <button
                        type="button"
                        wire:click="activateDomain('{{ $inspecting_domain_id }}')"
                        class="h-8 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <i class="fa-solid fa-shield-check text-xs"></i>
                        <span>{{ __('Verify & Activate Domain') }}</span>
                    </button>
                @endif
            </div>
        </div>
    @endif

    <!-- Toolbar Filters -->
    <div class="p-4 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('Search domain or operator name...') }}"
                class="h-10 w-full pl-9 pr-4 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50/50 dark:bg-zinc-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition shadow-2xs"
            />
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
            <div class="w-full sm:w-44">
                <x-select
                    wire:model.live="type_filter"
                    :options="[
                        'all' => __('All Types'),
                        'custom' => __('Custom Domains'),
                        'subdomain' => __('Subdomains'),
                    ]"
                />
            </div>
            <div class="w-full sm:w-48">
                <x-select
                    wire:model.live="status_filter"
                    :options="[
                        'all' => __('All Statuses'),
                        'active' => __('Active (SSL Issued)'),
                        'pending' => __('Pending DNS'),
                        'verifying' => __('Verifying'),
                        'failed' => __('Failed / Error'),
                    ]"
                />
            </div>
        </div>
    </div>

    <!-- Domains Table -->
    <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                <thead class="bg-slate-50 dark:bg-zinc-800/60 text-slate-900 dark:text-slate-200 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200/80 dark:border-zinc-800">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('Domain Hostname') }}</th>
                        <th class="px-5 py-3.5">{{ __('Assigned Operator') }}</th>
                        <th class="px-5 py-3.5">{{ __('Type') }}</th>
                        <th class="px-5 py-3.5">{{ __('SSL & Status') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('DNS Check & Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                    @forelse ($domains as $domain)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/40 transition">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-slate-900 dark:text-white text-xs">
                                        {{ $domain->domain }}
                                    </span>
                                    <a href="https://{{ $domain->domain }}" target="_blank" class="text-slate-400 hover:text-purple-600 dark:hover:text-purple-400 transition" title="{{ __('Visit Storefront') }}">
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                    </a>
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                @if ($domain->operator)
                                    <div class="space-y-0.5">
                                        <span class="font-bold text-slate-900 dark:text-slate-100">{{ $domain->operator->name }}</span>
                                        <p class="font-mono text-[10px] text-slate-400">#{{ $domain->operator->slug }}</p>
                                    </div>
                                @else
                                    <span class="text-slate-400 italic">{{ __('Unassigned') }}</span>
                                @endif
                            </td>

                            <td class="px-5 py-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $domain->type === DomainType::Custom ? 'bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300' : 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-slate-400' }}">
                                    {{ $domain->type === DomainType::Custom ? __('Custom Domain') : __('Subdomain') }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                @if ($domain->status === DomainStatus::Active)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <i class="fa-solid fa-lock text-[10px]"></i>
                                        {{ __('Active (SSL)') }}
                                    </span>
                                @elseif ($domain->status === DomainStatus::Pending || $domain->status === DomainStatus::Verifying)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-200 dark:border-amber-800 animate-pulse">
                                        <i class="fa-solid fa-hourglass-half text-[10px]"></i>
                                        {{ __('Pending DNS') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        <i class="fa-solid fa-circle-exclamation text-[10px]"></i>
                                        {{ __('DNS Failed') }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        wire:click="checkDns('{{ $domain->id }}')"
                                        class="h-8 px-2.5 rounded-xl bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/60 dark:hover:bg-purple-900/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800 font-bold text-xs shadow-2xs transition flex items-center gap-1.5 cursor-pointer"
                                        title="{{ __('Test Live DNS Resolution') }}"
                                    >
                                        <i class="fa-solid fa-satellite-dish text-[10px]" wire:loading.class="animate-spin" wire:target="checkDns('{{ $domain->id }}')"></i>
                                        <span>{{ __('Check DNS') }}</span>
                                    </button>

                                    @if ($domain->status !== DomainStatus::Active)
                                        <button
                                            type="button"
                                            wire:click="activateDomain('{{ $domain->id }}')"
                                            class="h-8 px-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition flex items-center gap-1 cursor-pointer"
                                            title="{{ __('Mark as Active & Verified') }}"
                                        >
                                            <i class="fa-solid fa-check text-[10px]"></i>
                                            <span>{{ __('Activate') }}</span>
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="setDomainStatus('{{ $domain->id }}', 'pending_dns')"
                                            class="h-8 px-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-slate-400 font-bold text-xs transition cursor-pointer"
                                            title="{{ __('Set to Pending DNS') }}"
                                        >
                                            <i class="fa-solid fa-pause text-[10px]"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-400">
                                <i class="fa-solid fa-globe text-3xl mb-2 block opacity-40"></i>
                                <p class="text-xs font-semibold">{{ __('No domains match your search or filter.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($domains->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-zinc-800">
                {{ $domains->links() }}
            </div>
        @endif
    </div>
</div>
