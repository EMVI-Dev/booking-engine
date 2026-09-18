@extends('errors.layout')

@section('title', __('Service Unavailable'))
@section('code', '503')
@section('icon', 'fa-solid fa-screwdriver-wrench')
@section('heading', __('Under maintenance'))
@section('message', ! empty($exception?->getMessage()) ? $exception->getMessage() : __('We are currently performing scheduled maintenance or quick updates. We will be back shortly.'))

@section('actions')
    <button type="button"
            onclick="window.location.reload()"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-sm font-bold transition duration-150 shadow-sm">
        <i class="fa-solid fa-rotate-right text-xs"></i>
        {{ __('Check again') }}
    </button>
    <a href="{{ url('/') }}"
       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-slate-300 text-sm font-semibold transition duration-150">
        <i class="fa-solid fa-house text-xs"></i>
        {{ __('Back home') }}
    </a>
@endsection
