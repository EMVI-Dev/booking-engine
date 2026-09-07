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
        ->assertDontSee('admin@travelengine.online / password')
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

test('platform admin can access payment gateways settings page', function () {
    $this->actingAs($this->adminUser)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertSee('Guest payments')
        ->assertSee('Checkout keys')
        ->assertSee('Client ID / API Key')
        ->assertSee('SNAP keys (optional)')
        ->assertSee('Webhook URL')
        ->assertSee('Save keys');
});

test('platform admin can update DOKU payment credentials', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.payments')
        ->set('doku_mode', 'live')
        ->set('sandbox_client_id', 'MALLID_SANDBOX_TEST')
        ->set('sandbox_secret_key', 'KEY_SANDBOX_TEST')
        ->set('sandbox_doku_public_key', 'DOKU_PUB_KEY')
        ->set('sandbox_merchant_public_key', 'MERCHANT_PUB_KEY')
        ->set('sandbox_merchant_private_key', 'MERCHANT_PRIV_KEY')
        ->set('sandbox_snap_token_url', 'https://api-sandbox.doku.com/authorization/v1/access-token/b2b')
        ->set('sandbox_base_url', 'https://api-sandbox.doku.com')
        ->set('sandbox_checkout_url', 'https://jokul-sandbox.doku.com/checkout')
        ->set('live_client_id', 'MALLID_LIVE_PROD')
        ->set('live_secret_key', 'KEY_LIVE_PROD')
        ->set('live_base_url', 'https://api.doku.com')
        ->set('live_checkout_url', 'https://jokul.doku.com/checkout')
        ->call('updatePaymentSettings')
        ->assertHasNoErrors();

    $settings = PlatformSetting::current()->fresh();

    expect($settings->getDokuMode()->value)->toBe('live')
        ->and($settings->getDokuSandboxClientId())->toBe('MALLID_SANDBOX_TEST')
        ->and($settings->getDokuSandboxSecretKey())->toBe('KEY_SANDBOX_TEST')
        ->and($settings->getDokuSandboxDokuPublicKey())->toBe('DOKU_PUB_KEY')
        ->and($settings->getDokuSandboxMerchantPublicKey())->toBe('MERCHANT_PUB_KEY')
        ->and($settings->getDokuLiveClientId())->toBe('MALLID_LIVE_PROD')
        ->and($settings->getDokuLiveSecretKey())->toBe('KEY_LIVE_PROD');
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
        ->assertSee('Guest payments');
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
