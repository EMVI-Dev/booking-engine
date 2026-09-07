<?php

use App\Models\User;

beforeEach(function () {
    $this->adminUser = User::factory()->create([
        'email' => 'admin@emvi.dev',
        'name' => 'Platform Admin',
        'is_admin' => true,
    ]);

    $this->actingAs($this->adminUser);
});

test('admin dashboard uses ebony palette and shared nav chrome', function () {
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('op-shell')
        ->assertSee('op-palette-ebony')
        ->assertSee('op-sidebar')
        ->assertSee('op-nav-item')
        ->assertSee('op-nav-icon')
        ->assertSee('op-hero')
        ->assertSee('op-metric')
        ->assertSee('op-metric-featured')
        ->assertSee(__('Admin'))
        ->assertSee(config('app.name', 'TravelEngine'))
        ->assertSee('by EMVI Technologies')
        ->assertSee(__('Dashboard'))
        ->assertSee(__('Operators'))
        ->assertSee(__('Operator Portal'))
        ->assertSee(__('Money overview'))
        ->assertSee(__('All guest payments'))
        ->assertSee(__('Plan fees this month'))
        ->assertDontSee('bg-[#FFEF4D] text-[#090d16] font-black shadow-xs', false);
});

test('admin operators and settings share the same dry chrome tokens', function () {
    $this->get(route('admin.operators.index'))
        ->assertOk()
        ->assertSee('op-palette-ebony')
        ->assertSee('op-toolbar')
        ->assertSee('op-tab')
        ->assertSee(__('Operators'));

    $this->get(route('admin.platform.edit'))
        ->assertOk()
        ->assertSee('op-palette-ebony')
        ->assertSee('op-nav-item')
        ->assertSee(__('Settings'))
        ->assertSee(__('operators active'));
});

test('admin payouts page is available', function () {
    $this->get(route('admin.payouts.index'))
        ->assertOk()
        ->assertSee(__('Payouts'));
});
