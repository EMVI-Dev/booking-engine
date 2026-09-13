<x-modal name="choose-activity-cover" maxWidth="lg" focusable>
    <div class="p-5 sm:p-6 space-y-4">
        <div class="space-y-1">
            <h3 class="text-base font-bold text-op-ink">
                {{ __('Choose an activity cover') }}
            </h3>
            <p class="text-xs text-op-subtle">
                {{ __('Pick a cover from an activity in this package. You can still upload a custom cover anytime.') }}
            </p>
        </div>

        @if ($this->activityCoverChoices->isEmpty())
            <div class="p-6 rounded-2xl border border-dashed border-op-line text-center text-xs text-op-subtle">
                {{ __('None of the selected activities have a cover photo yet.') }}
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[60vh] overflow-y-auto overscroll-contain pr-0.5">
                @foreach ($this->activityCoverChoices as $activity)
                    <button
                        type="button"
                        wire:key="activity-cover-{{ $activity->id }}"
                        wire:click="applyActivityCover('{{ $activity->id }}')"
                        class="group text-left rounded-2xl border border-op-line bg-op-muted/60 hover:border-brand-400 overflow-hidden transition cursor-pointer"
                    >
                        <div class="aspect-video bg-op-muted overflow-hidden">
                            <img
                                src="{{ $this->mediaUrl($activity->cover_photo) }}"
                                alt="{{ $activity->name }}"
                                class="w-full h-full object-cover group-hover:scale-[1.02] transition"
                            />
                        </div>
                        <div class="px-3 py-2.5 flex items-center justify-between gap-2">
                            <span class="text-xs font-bold text-op-ink truncate">{{ $activity->name }}</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-op-ink shrink-0">
                                {{ __('Use') }}
                            </span>
                        </div>
                    </button>
                @endforeach
            </div>
        @endif

        <div class="flex justify-end pt-1">
            <x-button
                type="button"
                variant="secondary"
                x-on:click="$dispatch('close-modal', 'choose-activity-cover')"
                class="font-semibold text-xs"
            >
                {{ __('Cancel') }}
            </x-button>
        </div>
    </div>
</x-modal>
