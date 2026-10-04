<?php

use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\User;
use Livewire\Livewire;

function teammate(OperatorUserRole $role): array
{
    $operator = Operator::factory()->create();
    $user = User::factory()->create();
    $operator->users()->attach($user->id, ['role' => $role->value]);

    return [$operator, $user];
}

test('a bookings teammate cannot open money, payout bank, vendor or shop settings pages', function (string $page) {
    [, $user] = teammate(OperatorUserRole::Reservation);

    $this->actingAs($user);

    Livewire::test($page)->assertForbidden();
})->with([
    'pages::wallet.index',
    'pages::settings.payments',
    'pages::settings.billing',
    'pages::vendors.index',
    'pages::settings.storefront',
    'pages::settings.brand',
]);

test('a money teammate can open the wallet and payout bank but not shop settings', function () {
    [, $user] = teammate(OperatorUserRole::Finance);

    $this->actingAs($user);

    Livewire::test('pages::wallet.index')->assertOk();
    Livewire::test('pages::settings.payments')->assertOk();

    Livewire::test('pages::settings.storefront')->assertForbidden();
});

test('a money teammate cannot block calendar dates', function () {
    [, $user] = teammate(OperatorUserRole::Finance);

    $this->actingAs($user);

    Livewire::test('calendar.month-grid')->call('saveBlackoutBlock')->assertForbidden();
});
