{{--
    Global toast host.

    Mount once per layout. Picks up two sources:
      1. Session flash keys: success, error, warning, status
      2. Livewire/browser events: $this->dispatch('toast', message: '...', type: 'success')

    Toasts announce themselves to screen readers and auto-dismiss unless they are errors,
    which stay until dismissed so a failure is never missed.
--}}
<div
    x-data="{
        toasts: [],
        add(detail) {
            const message = detail?.message ?? detail?.[0]?.message
            if (! message) return

            const id = Date.now() + Math.random()
            const type = detail?.type ?? detail?.[0]?.type ?? 'success'

            this.toasts.push({ id, message, type })

            if (type !== 'error') {
                setTimeout(() => this.dismiss(id), 5000)
            }
        },
        dismiss(id) {
            this.toasts = this.toasts.filter(toast => toast.id !== id)
        },
        icon(type) {
            return {
                success: 'fa-circle-check',
                error: 'fa-circle-exclamation',
                warning: 'fa-triangle-exclamation',
                info: 'fa-circle-info',
            }[type] ?? 'fa-circle-check'
        },
        tone(type) {
            return {
                success: 'bg-emerald-50 dark:bg-emerald-950/80 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200',
                error: 'bg-rose-50 dark:bg-rose-950/80 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200',
                warning: 'bg-amber-50 dark:bg-amber-950/80 border-amber-200 dark:border-amber-800 text-amber-900 dark:text-amber-200',
                info: 'bg-sky-50 dark:bg-sky-950/80 border-sky-200 dark:border-sky-800 text-sky-800 dark:text-sky-200',
            }[type] ?? 'bg-emerald-50 dark:bg-emerald-950/80 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200'
        },
    }"
    x-init="
        @if (session('success')) add({ message: @js(session('success')), type: 'success' }); @endif
        @if (session('status')) add({ message: @js(session('status')), type: 'success' }); @endif
        @if (session('error')) add({ message: @js(session('error')), type: 'error' }); @endif
        @if (session('warning')) add({ message: @js(session('warning')), type: 'warning' }); @endif
    "
    @toast.window="add($event.detail)"
    class="fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4 pointer-events-none print:hidden sm:inset-x-auto sm:right-6 sm:items-end"
    role="region"
    aria-label="{{ __('Notifications') }}"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2 sm:translate-x-2 sm:translate-y-0"
            x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="pointer-events-auto w-full sm:w-auto sm:min-w-80 sm:max-w-md flex items-start gap-3 rounded-2xl border px-4 py-3 shadow-lg backdrop-blur-sm"
            :class="tone(toast.type)"
            role="alert"
            aria-live="polite"
        >
            <i class="fa-solid mt-0.5 shrink-0 text-sm" :class="icon(toast.type)" aria-hidden="true"></i>
            <p class="flex-1 text-xs font-semibold leading-relaxed" x-text="toast.message"></p>
            <button
                type="button"
                @click="dismiss(toast.id)"
                class="shrink-0 rounded-lg p-1 opacity-60 transition hover:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 cursor-pointer"
                aria-label="{{ __('Dismiss notification') }}"
            >
                <i class="fa-solid fa-xmark text-xs" aria-hidden="true"></i>
            </button>
        </div>
    </template>
</div>
