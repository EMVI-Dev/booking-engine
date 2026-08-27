<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/brand');

    Route::livewire('settings/brand', 'pages::settings.brand')->name('brand.edit');
    Route::livewire('settings/storefront', 'pages::settings.storefront')->name('storefront-settings.edit');
    Route::livewire('settings/payments', 'pages::settings.payments')->name('payments.edit');
    Route::livewire('settings/plan', 'pages::settings.plan')->name('settings.plan');
    Route::livewire('settings/plan/checkout/{payment}', 'pages::settings.plan-checkout')->name('settings.plan.checkout');
    Route::livewire('settings/billing', 'pages::settings.billing')->name('settings.billing');
    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
