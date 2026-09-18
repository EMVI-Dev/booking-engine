@extends('errors.layout')

@section('title', __('Unauthorized'))
@section('code', '401')
@section('icon', 'fa-solid fa-user-lock')
@section('heading', __('Authentication required'))
@section('message', ! empty($exception?->getMessage()) ? $exception->getMessage() : __('You must be signed in with an authorized account to view this page.'))

@section('actions')
    <a href="{{ route('login') }}"
       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-[#FFEF4D] hover:bg-[#F3E13A] text-[#12181E] text-sm font-bold transition duration-150 shadow-sm">
        <i class="fa-solid fa-right-to-bracket text-xs"></i>
        {{ __('Sign in') }}
    </a>
    <a href="{{ url('/') }}"
       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-slate-300 text-sm font-semibold transition duration-150">
        <i class="fa-solid fa-house text-xs"></i>
        {{ __('Back home') }}
    </a>
@endsection
