@extends('errors.layout')

@section('title', __('Access Forbidden'))
@section('code', '403')
@section('icon', 'fa-solid fa-shield-halved')
@section('heading', __('Access restricted'))
@section('message', ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $exception->getPrevious() === null && filled($exception->getMessage())) ? $exception->getMessage() : __('You do not have permission to access this area or perform this action.'))
