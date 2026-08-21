<?php

use App\Models\Operator;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Guest Reviews')] class extends Component {
    public string $search = '';
    public string $ratingFilter = 'all'; // all, 5, 4, 3, low

    #[Computed]
    public function currentOperator(): ?Operator
    {
        return Auth::user()?->currentOperator();
    }

    #[Computed]
    public function currentAgent(): ?Operator
    {
        return $this->currentOperator;
    }

    /**
     * Get aggregate review rating statistics for agent.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function statistics(): array
    {
        if (! $this->currentAgent) {
            return [
                'count' => 0,
                'average' => 0.0,
                'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
            ];
        }

        $reviews = $this->currentAgent->reviews()->get();
        $count = $reviews->count();

        if ($count === 0) {
            return [
                'count' => 0,
                'average' => 0.0,
                'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
            ];
        }

        $average = round((float) $reviews->avg('rating'), 1);

        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($reviews as $r) {
            $val = (int) $r->rating;
            if (isset($dist[$val])) {
                $dist[$val]++;
            }
        }

        return [
            'count' => $count,
            'average' => $average,
            'distribution' => $dist,
        ];
    }

    /**
     * Get filtered reviews collection.
     *
     * @return Collection<int, Review>
     */
    #[Computed]
    public function reviews(): Collection
    {
        if (! $this->currentAgent) {
            return new Collection();
        }

        $query = $this->currentAgent->reviews()
            ->with(['bookable', 'reservation'])
            ->latest();

        if ($this->ratingFilter === '5') {
            $query->where('rating', 5);
        } elseif ($this->ratingFilter === '4') {
            $query->where('rating', 4);
        } elseif ($this->ratingFilter === '3') {
            $query->where('rating', 3);
        } elseif ($this->ratingFilter === 'low') {
            $query->whereIn('rating', [1, 2]);
        }

        if (trim($this->search) !== '') {
            $search = '%' . trim($this->search) . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('comment', 'like', $search)
                    ->orWhereHas('reservation', fn (Builder $resQ) => $resQ->where('guest_name', 'like', $search));
            });
        }

        return $query->get();
    }
}; ?>

<div class="space-y-8 animate-fade-in">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/70 text-amber-600 dark:text-amber-400">
                    <i class="fa-solid fa-star text-lg"></i>
                </span>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ __('Guest Reviews') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Verified feedback and experience ratings submitted by guests after completed reservations.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                {{ __(':count Verified Reviews', ['count' => $this->statistics['count']]) }}
            </span>
        </div>
    </div>

    <!-- Rating Summary Metrics & Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Score Card -->
        <div class="rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs p-6 flex flex-col items-center justify-center text-center space-y-3">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                {{ __('Overall Satisfaction') }}
            </span>
            <div class="text-5xl font-black text-slate-900 dark:text-white flex items-baseline gap-1">
                <span>{{ number_format((float) $this->statistics['average'], 1) }}</span>
                <span class="text-sm text-slate-400 font-normal">/ 5.0</span>
            </div>
            <div class="flex items-center gap-1 text-amber-400 text-base">
                @for ($i = 1; $i <= 5; $i++)
                    <i class="fa-solid fa-star {{ $i <= round($this->statistics['average']) ? 'text-amber-400' : 'text-slate-200 dark:text-zinc-700' }}"></i>
                @endfor
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('Based on :count authentic reviews', ['count' => $this->statistics['count']]) }}
            </p>
        </div>

        <!-- Rating Distribution Progress Bars -->
        <div class="lg:col-span-2 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs p-6 space-y-2.5 flex flex-col justify-center">
            @php
                $total = max(1, $this->statistics['count']);
            @endphp
            @foreach ([5, 4, 3, 2, 1] as $stars)
                @php
                    $count = $this->statistics['distribution'][$stars] ?? 0;
                    $pct = ($count / $total) * 100;
                @endphp
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-12 font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                        {{ $stars }} <i class="fa-solid fa-star text-[10px] text-amber-400"></i>
                    </span>
                    <div class="flex-1 h-2 rounded-full bg-slate-100 dark:bg-zinc-800 overflow-hidden">
                        <div
                            class="h-full rounded-full {{ $stars >= 4 ? 'bg-amber-400' : ($stars === 3 ? 'bg-amber-500' : 'bg-slate-400') }} transition-all duration-300"
                            style="width: {{ $pct }}%"
                        ></div>
                    </div>
                    <span class="w-10 text-right text-slate-400 font-mono text-[11px]">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </div>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="{{ __('Search by guest name or review keywords...') }}"
                    class="h-10 w-full pl-9 pr-4 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50/50 dark:bg-zinc-800 text-xs sm:text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition"
                />
            </div>

            <!-- Rating Filter Tabs -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                @foreach (['all' => __('All'), '5' => '5 ★', '4' => '4 ★', '3' => '3 ★', 'low' => '1-2 ★'] as $rKey => $rLabel)
                    <button
                        type="button"
                        wire:click="$set('ratingFilter', '{{ $rKey }}')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer {{ $ratingFilter === $rKey ? 'bg-amber-500 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800' }}"
                    >
                        {{ $rLabel }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Reviews Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse ($this->reviews as $rev)
            @php
                $res = $rev->reservation;
                $bookable = $rev->bookable;
            @endphp
            <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 shadow-xs space-y-3.5 flex flex-col justify-between">
                <div class="space-y-2.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-xs">
                                {{ strtoupper(substr($res->guest_name ?? 'G', 0, 1)) }}
                            </span>
                            <div>
                                <h4 class="font-bold text-sm text-slate-900 dark:text-white">
                                    {{ $res->guest_name ?? __('Verified Guest') }}
                                </h4>
                                <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                                    <i class="fa-solid fa-circle-check text-[9px]"></i>
                                    {{ __('Verified Stay / Trip') }}
                                </span>
                            </div>
                        </div>

                        <!-- Stars -->
                        <div class="flex items-center gap-0.5 text-amber-400 text-xs">
                            @for ($s = 1; $s <= 5; $s++)
                                <i class="fa-solid fa-star {{ $s <= $rev->rating ? 'text-amber-400' : 'text-slate-200 dark:text-zinc-700' }}"></i>
                            @endfor
                        </div>
                    </div>

                    <!-- Comment -->
                    @if ($rev->comment)
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed italic">
                            &ldquo;{{ $rev->comment }}&rdquo;
                        </p>
                    @endif
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between text-xs text-slate-400">
                    <span class="truncate max-w-[180px] font-semibold text-slate-700 dark:text-slate-300">
                        {{ $bookable->name ?? ($bookable->title ?? 'Experience') }}
                    </span>
                    <span>{{ $rev->created_at?->format('M d, Y') ?? 'Recent' }}</span>
                </div>
            </div>
        @empty
            <div class="col-span-full p-12 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200/80 dark:border-zinc-800 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto text-xl">
                    <i class="fa-solid fa-star"></i>
                </div>
                <h4 class="font-bold text-slate-800 dark:text-slate-200">{{ __('No reviews found') }}</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                    {{ __('Verified reviews left by guests after completing trips will be displayed here.') }}
                </p>
            </div>
        @endforelse
    </div>
</div>
