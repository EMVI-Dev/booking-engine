<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('Something went wrong') }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 px-4 text-slate-900 antialiased dark:bg-ebony dark:text-white">
    <div class="max-w-md space-y-3 text-center">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">500</p>
        <h1 class="text-2xl font-black tracking-tight">{{ __('Something went wrong') }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('Try again in a moment. If it keeps happening, message the team.') }}</p>
        <a href="{{ url('/') }}" class="inline-flex text-sm font-bold underline">{{ __('Back home') }}</a>
    </div>
</body>
</html>
