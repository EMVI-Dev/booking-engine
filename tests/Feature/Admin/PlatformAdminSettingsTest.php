<?php

use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->create([
        'email' => 'admin@emvi.dev',
        'name' => 'Platform Admin',
        'password' => bcrypt('password'),
        'is_admin' => true,
    ]);

    $this->regularUser = User::factory()->create([
        'email' => 'operator@agency.com',
        'name' => 'Regular Operator',
        'password' => bcrypt('password'),
        'is_admin' => false,
    ]);
});

test('unauthenticated guest accessing admin dashboard is redirected to admin login', function () {
    $this->get(route('admin.platform.edit'))
        ->assertRedirect(route('admin.login'));
});

test('admin login page is rendered for guests', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Admin sign in')
        ->assertSee('Admin')
        ->assertDontSee('admin@travelengine.id / password')
        ->assertDontSee('Auto-fill');
});

test('non-admin user attempting admin login is rejected', function () {
    Livewire::test('pages::admin.login')
        ->set('email', 'operator@agency.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

test('platform admin can authenticate via admin login and is redirected to admin dashboard', function () {
    Livewire::test('pages::admin.login')
        ->set('email', 'admin@emvi.dev')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($this->adminUser);
});

test('non-admin user accessing admin settings directly receives 403 forbidden', function () {
    $this->actingAs($this->regularUser)
        ->get(route('admin.platform.edit'))
        ->assertForbidden();

    $this->actingAs($this->regularUser)
        ->get(route('admin.profile.edit'))
        ->assertForbidden();
});

test('platform admin can access dedicated admin profile and security page', function () {
    $this->actingAs($this->adminUser)
        ->get(route('admin.profile.edit'))
        ->assertOk()
        ->assertSee('Your profile')
        ->assertSee('Your details')
        ->assertSee('Update Password')
        ->assertSee('Two-Factor Authentication')
        ->assertSee('Passkeys');
});

test('platform admin can update their profile information and password from admin profile page', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.profile')
        ->set('name', 'Super Administrator')
        ->set('email', 'superadmin@emvi.dev')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($this->adminUser->fresh()->name)->toBe('Super Administrator')
        ->and($this->adminUser->fresh()->email)->toBe('superadmin@emvi.dev');

    Livewire::test('pages::admin.profile')
        ->set('current_password', 'password')
        ->set('password', 'new-super-secret-password-123')
        ->set('password_confirmation', 'new-super-secret-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-super-secret-password-123', $this->adminUser->fresh()->password))->toBeTrue();
});

test('platform admin can access platform settings page', function () {
    $this->actingAs($this->adminUser)
        ->get(route('admin.platform.edit'))
        ->assertOk()
        ->assertSee('Settings')
        ->assertSee('Platform maintenance')
        ->assertSee('Name, fees, and currency');
});

test('platform admin can update global platform settings', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.platform')
        ->set('platform_name', 'TravelEngine Global Platform')
        ->set('support_email', 'ops@emvi.dev')
        ->set('commission_percentage', 12.5)
        ->set('booking_hold_minutes', 45)
        ->set('currency_code', 'IDR')
        ->set('currency_symbol', 'Rp')
        ->call('updatePlatformSettings')
        ->assertHasNoErrors();

    $settings = PlatformSetting::current()->fresh();

    expect($settings->getPlatformName())->toBe('TravelEngine Global Platform')
        ->and($settings->getSupportEmail())->toBe('ops@emvi.dev')
        ->and($settings->getCommissionRate())->toBe(0.125)
        ->and($settings->getBookingHoldMinutes())->toBe(45);
});

test('platform admin must confirm before turning on platform maintenance', function () {
    config(['fortify.registration_enabled' => true]);

    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.platform')
        ->assertSet('platform_maintenance', false)
        ->call('requestMaintenanceToggle')
        ->assertSet('confirming_maintenance', true)
        ->assertSet('pending_maintenance', true)
        ->assertSet('platform_maintenance', false)
        ->assertDispatched('open-modal', 'confirm-platform-maintenance')
        ->assertSee('Turn on platform maintenance?')
        ->assertSee('Pause bookings')
        ->call('confirmMaintenanceToggle')
        ->assertSet('platform_maintenance', true)
        ->assertSet('confirming_maintenance', false)
        ->assertDispatched('close-modal', 'confirm-platform-maintenance');

    expect(PlatformSetting::current()->fresh()->isPlatformMaintenance())->toBeTrue();

    auth()->logout();

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Operator sign-up is paused')
        ->assertDontSee('Create account');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign In to Operator Portal');
});

test('cancelling platform maintenance confirmation leaves bookings open', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.platform')
        ->call('requestMaintenanceToggle')
        ->assertSet('confirming_maintenance', true)
        ->assertSet('pending_maintenance', true)
        ->call('cancelMaintenanceToggle')
        ->assertSet('platform_maintenance', false)
        ->assertSet('confirming_maintenance', false)
        ->assertDispatched('close-modal', 'confirm-platform-maintenance');

    expect(PlatformSetting::current()->fresh()->isPlatformMaintenance())->toBeFalse();
});

test('platform admin must confirm before turning off platform maintenance', function () {
    PlatformSetting::current()->setPlatformMaintenance(true);

    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.platform')
        ->assertSet('platform_maintenance', true)
        ->call('requestMaintenanceToggle')
        ->assertSet('confirming_maintenance', true)
        ->assertSet('pending_maintenance', false)
        ->assertSee('Turn off platform maintenance?')
        ->assertSee('Resume bookings')
        ->call('confirmMaintenanceToggle')
        ->assertSet('platform_maintenance', false)
        ->assertSet('confirming_maintenance', false);

    expect(PlatformSetting::current()->fresh()->isPlatformMaintenance())->toBeFalse();

    auth()->logout();

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create account');
});

test('saving platform settings does not change maintenance without confirmation', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.platform')
        ->set('platform_maintenance', true)
        ->call('updatePlatformSettings')
        ->assertHasNoErrors()
        ->assertSet('platform_maintenance', true);

    expect(PlatformSetting::current()->fresh()->isPlatformMaintenance())->toBeFalse();
});

test('platform setting resolves doku mode strictly from config', function () {
    config(['doku.default_mode' => 'live']);
    expect(PlatformSetting::current()->getDokuMode()->value)->toBe('live');

    config(['doku.default_mode' => 'sandbox']);
    expect(PlatformSetting::current()->getDokuMode()->value)->toBe('sandbox');
});

test('platform setting ignores database values for doku mode', function () {
    config(['doku.default_mode' => 'sandbox']);

    $platform = PlatformSetting::current();
    $platform->update([
        'settings' => [
            'doku_mode' => 'live',
            'doku' => ['mode' => 'live'],
        ],
    ]);

    expect($platform->fresh()->getDokuMode()->value)->toBe('sandbox');
});

test('platform settings reads doku credentials strictly from config and ignores database values', function () {
    config([
        'doku.sandbox.client_id' => 'cfg-sandbox-client',
        'doku.sandbox.secret_key' => 'cfg-sandbox-secret',
        'doku.live.client_id' => 'cfg-live-client',
        'doku.live.secret_key' => 'cfg-live-secret',
    ]);

    $platform = PlatformSetting::current();
    $platform->update([
        'settings' => [
            'doku' => [
                'sandbox' => [
                    'client_id' => 'db-leaked-client',
                    'secret_key' => 'db-leaked-secret',
                ],
                'live' => [
                    'client_id' => 'db-leaked-client',
                    'secret_key' => 'db-leaked-secret',
                ],
            ],
        ],
    ]);

    $fresh = $platform->fresh();
    expect($fresh->getDokuSandboxClientId())->toBe('cfg-sandbox-client')
        ->and($fresh->getDokuSandboxSecretKey())->toBe('cfg-sandbox-secret')
        ->and($fresh->getDokuLiveClientId())->toBe('cfg-live-client')
        ->and($fresh->getDokuLiveSecretKey())->toBe('cfg-live-secret');
});

test('platform admin can view operators management directory', function () {
    $operator = Operator::factory()->create([
        'name' => 'Nusa Penida Charters',
        'slug' => 'penida-charters',
        'status' => OperatorStatus::Approved,
        'bank_provider' => 'BCA',
        'bank_account_number' => '555111222',
        'bank_account_name' => 'PT Penida Charters',
    ]);

    $this->actingAs($this->adminUser)
        ->get(route('admin.operators.index'))
        ->assertOk()
        ->assertSee('Operators')
        ->assertSee('Nusa Penida Charters')
        ->assertSee('penida-charters');

    $this->actingAs($this->adminUser)
        ->get(route('admin.operators.show', $operator->id))
        ->assertOk()
        ->assertSee('555111222');
});

test('platform admin can search operators and update approval status', function () {
    $operator = Operator::factory()->create([
        'name' => 'Komodo Diving Co',
        'slug' => 'komodo-diving',
        'status' => OperatorStatus::Pending,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.operators.index')
        ->set('search', 'Komodo')
        ->assertSee('Komodo Diving Co')
        ->call('updateStatus', $operator->id, 'approved')
        ->assertHasNoErrors();

    expect($operator->fresh()->status)->toBe(OperatorStatus::Approved);

    Livewire::test('pages::admin.operators.index')
        ->call('updateStatus', $operator->id, 'suspended')
        ->assertHasNoErrors();

    expect($operator->fresh()->status)->toBe(OperatorStatus::Suspended);
});

test('platform admin can choose which operator to manage and switch into their portal', function () {
    $operatorA = Operator::factory()->create(['name' => 'Operator Alpha', 'slug' => 'operator-alpha']);
    $operatorB = Operator::factory()->create(['name' => 'Operator Beta', 'slug' => 'operator-beta']);

    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.operators.index')
        ->call('manageOperator', $operatorB->id)
        ->assertRedirect(route('dashboard'));

    expect(session('admin_impersonated_operator_id'))->toBe($operatorB->id)
        ->and($this->adminUser->currentOperator()->id)->toBe($operatorB->id);
});

test('platform admin can access dedicated operator details page and view insights', function () {
    $operator = Operator::factory()->create([
        'name' => 'Bali Sea Explorers',
        'slug' => 'bali-sea-explorers',
        'status' => OperatorStatus::Approved,
        'bank_provider' => 'BCA',
        'bank_account_number' => '999888777',
        'bank_account_name' => 'PT Sea Explorers',
    ]);

    $this->actingAs($this->adminUser)
        ->get(route('admin.operators.show', $operator->id))
        ->assertOk()
        ->assertSee('Bali Sea Explorers')
        ->assertSee('999888777')
        ->assertSee('PT Sea Explorers')
        ->assertSee('Open their dashboard')
        ->assertSee('Settings');
});

test('platform admin can update operator status and plan from dedicated operator details page', function () {
    $operator = Operator::factory()->create([
        'name' => 'Lombok Trekking Co',
        'slug' => 'lombok-trekking',
        'status' => OperatorStatus::Pending,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.operators.show', ['operator' => $operator])
        ->call('updateStatus', 'approved')
        ->assertHasNoErrors();

    expect($operator->fresh()->status)->toBe(OperatorStatus::Approved);

    Livewire::test('pages::admin.operators.show', ['operator' => $operator])
        ->call('manageOperator')
        ->assertRedirect(route('dashboard'));

    expect(session('admin_impersonated_operator_id'))->toBe($operator->id);
});
