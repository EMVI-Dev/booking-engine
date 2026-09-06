@if (session('error') || session('success') || session('status'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/50 px-4 py-3 text-sm font-semibold text-rose-700 dark:text-rose-300" role="alert">
                {{ session('error') }}
            </div>
        @endif
        @if (session('success') || session('status'))
            <div class="rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:text-emerald-300" role="status">
                {{ session('success') ?? session('status') }}
            </div>
        @endif
    </div>
@endif
