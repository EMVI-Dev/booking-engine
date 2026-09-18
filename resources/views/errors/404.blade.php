@extends('errors.layout')

@section('title', __('Page Not Found'))
@section('code', '404')
@section('icon', 'fa-solid fa-compass-drafting')
@section('heading', __('This page is not here'))
@section('message', ! empty($exception?->getMessage()) ? $exception->getMessage() : __('The link may be old, the booking page moved, or the address was typed incorrectly.'))
