<?php

use App\Http\Controllers\Api\DokuWebhookController;
use App\Http\Controllers\CaddyAskController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Storefront & Platform Home
Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/tours', [StorefrontController::class, 'allPackages'])->name('storefront.packages');
Route::get('/services', [StorefrontController::class, 'allProducts'])->name('storefront.products');
Route::get('/terms', [StorefrontController::class, 'showTerms'])->name('storefront.terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/robots.txt', [StorefrontController::class, 'robots'])->name('storefront.robots');
Route::get('/sitemap.xml', [StorefrontController::class, 'sitemap'])->name('storefront.sitemap');
Route::get('/llms.txt', [StorefrontController::class, 'llmsTxt'])->name('storefront.llms');
Route::get('/llms-full.txt', [StorefrontController::class, 'llmsFullTxt'])->name('storefront.llms.full');
Route::get('/checkout/simulate', [StorefrontController::class, 'simulatePayment'])->name('storefront.payment.simulate');
Route::post('/checkout/simulate/confirm', [StorefrontController::class, 'confirmSimulatedPayment'])->name('storefront.payment.simulate.confirm');
Route::get('/reservations/{reservation}/receipt', [StorefrontController::class, 'showReceipt'])->name('storefront.reservation.receipt');
Route::get('/reservations/{reservation}/e-ticket', [StorefrontController::class, 'showTicket'])->name('storefront.reservation.ticket');
Route::get('/reservations/{reservation}/pay', [StorefrontController::class, 'payReservation'])->name('storefront.reservation.pay');
Route::post('/reservations/{reservation}/cancel', [StorefrontController::class, 'cancelReservation'])
    ->middleware('throttle:10,1')
    ->name('storefront.reservation.cancel');
Route::get('/find-booking', [StorefrontController::class, 'findBooking'])->name('storefront.find-booking');
Route::post('/find-booking', [StorefrontController::class, 'lookupBooking'])
    ->middleware('throttle:10,1')
    ->name('storefront.find-booking.lookup');

// Live iCal Calendar Feed for Google / Apple / Outlook Subscriptions
Route::get('/calendar/feed/{token}', [CalendarFeedController::class, 'feed'])->name('calendar.feed');

// DOKU Webhook Notification Endpoint
Route::post('/api/v1/payments/doku/notify', [DokuWebhookController::class, 'handleNotification'])
    ->middleware('throttle:60,1')
    ->name('doku.webhook')
    ->withoutMiddleware([ValidateCsrfToken::class]);

Route::get('/internal/caddy/ask', CaddyAskController::class)
    ->middleware('throttle:60,1')
    ->name('caddy.ask');

// Agent Dashboard & Tour Operator Catalog Management
Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('packages', 'pages::packages.index')->name('packages.index');
    Route::livewire('packages/create', 'pages::packages.create')->name('packages.create');
    Route::livewire('packages/{package}/edit', 'pages::packages.edit')->name('packages.edit');

    Route::livewire('products', 'pages::products.index')->name('products.index');
    Route::livewire('products/create', 'pages::products.create')->name('products.create');
    Route::livewire('products/{product}/edit', 'pages::products.edit')->name('products.edit');

    Route::livewire('reservations', 'pages::reservations.index')->name('reservations.index');
    Route::livewire('guests', 'pages::guests.index')->name('guests.index');
    Route::livewire('calendar', 'pages::calendar.index')->name('calendar.index');
    Route::livewire('reviews', 'pages::reviews.index')->name('reviews.index');
    Route::livewire('wallet', 'pages::wallet.index')->name('wallet.index');
    Route::livewire('coupons', 'pages::coupons.index')->name('coupons.index');
});

// Public Storefront Item Details (Wildcard Slugs)
Route::get('/packages/{slug}', [StorefrontController::class, 'showPackage'])->name('storefront.package');
Route::get('/products/{slug}', [StorefrontController::class, 'showProduct'])->name('storefront.product');

// Platform Administration (Master Doku Keys, Platform Config & Separate Admin Auth)
Route::middleware('platform')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::livewire('admin/login', 'pages::admin.login')->name('admin.login');
    });

    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
        Route::livewire('/dashboard', 'pages::admin.dashboard');
        Route::livewire('/operators', 'pages::admin.operators.index')->name('operators.index');
        Route::livewire('/operators/{operator}', 'pages::admin.operators.show')->name('operators.show');
        Route::livewire('/plans', 'pages::admin.plans')->name('plans.index');
        Route::livewire('/announcements', 'pages::admin.announcements')->name('announcements.index');
        Route::livewire('/coupons', 'pages::admin.coupons')->name('coupons.index');
        Route::livewire('/payouts', 'pages::admin.payouts')->name('payouts.index');
        Route::livewire('/payments', 'pages::admin.payments')->name('payments.index');
        Route::livewire('/platform', 'pages::admin.platform')->name('platform.edit');
        Route::livewire('/profile', 'pages::admin.profile')->name('profile.edit');
    });
});

require __DIR__.'/settings.php';
