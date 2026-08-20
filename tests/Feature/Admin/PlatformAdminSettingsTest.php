<?php

use App\Enums\AgentStatus;
use App\Models\Agent;
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
        ->assertSee('Platform Admin Login')
        ->assertSee('Platform Master Control');
});

test('non-admin user attempting admin login is rejected', function () {
    Livewire::test('pages::admin.login')
        ->set('email', 'operator@agency.com')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

test('platform admin can authenticate via admin login and is redirected to admin platform edit', function () {
    Livewire::test('pages::admin.login')
        ->set('email', 'admin@emvi.dev')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.platform.edit'));

    $this->assertAuthenticatedAs($this->adminUser);
});

test('non-admin user accessing admin settings directly receives 403 forbidden', function () {
    $this->actingAs($this->regularUser)
        ->get(route('admin.platform.edit'))
        ->assertForbidden();
});

test('platform admin can access platform settings page', function () {
    $this->actingAs($this->adminUser)
        ->get(route('admin.platform.edit'))
        ->assertOk()
        ->assertSee('Platform Settings')
        ->assertSee('Platform Identity & Global Economics');
});

test('platform admin can update global platform settings', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.platform')
        ->set('platform_name', 'Emvi Global Travel Platform')
        ->set('support_email', 'ops@emvi.dev')
        ->set('commission_percentage', 12.5)
        ->set('booking_hold_minutes', 45)
        ->set('currency_code', 'IDR')
        ->set('currency_symbol', 'Rp')
        ->call('updatePlatformSettings')
        ->assertHasNoErrors();

    $settings = PlatformSetting::current()->fresh();

    expect($settings->getPlatformName())->toBe('Emvi Global Travel Platform')
        ->and($settings->getSupportEmail())->toBe('ops@emvi.dev')
        ->and($settings->getCommissionRate())->toBe(0.125)
        ->and($settings->getBookingHoldMinutes())->toBe(45);
});

test('platform admin can access payment gateways settings page', function () {
    $this->actingAs($this->adminUser)
        ->get(route('admin.payments.edit'))
        ->assertOk()
        ->assertSee('Payment Gateways')
        ->assertSee('Central DOKU Payment Credentials');
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

test('platform admin can view operators and agents management directory', function () {
    $agent = Agent::factory()->create([
        'name' => 'Nusa Penida Charters',
        'slug' => 'penida-charters',
        'status' => AgentStatus::Approved,
        'bank_provider' => 'BCA',
        'bank_account_number' => '555111222',
        'bank_account_name' => 'PT Penida Charters',
    ]);

    $this->actingAs($this->adminUser)
        ->get(route('admin.agents.index'))
        ->assertOk()
        ->assertSee('Agents Management')
        ->assertSee('Nusa Penida Charters')
        ->assertSee('555111222');
});

test('platform admin can search agents and update approval status', function () {
    $agent = Agent::factory()->create([
        'name' => 'Komodo Diving Co',
        'slug' => 'komodo-diving',
        'status' => AgentStatus::Pending,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.agents.index')
        ->set('search', 'Komodo')
        ->assertSee('Komodo Diving Co')
        ->call('updateStatus', $agent->id, 'approved')
        ->assertHasNoErrors();

    expect($agent->fresh()->status)->toBe(AgentStatus::Approved);

    Livewire::test('pages::admin.agents.index')
        ->call('updateStatus', $agent->id, 'suspended')
        ->assertHasNoErrors();

    expect($agent->fresh()->status)->toBe(AgentStatus::Suspended);
});

test('platform admin can choose which agent to manage and switch into their portal', function () {
    $agentA = Agent::factory()->create(['name' => 'Agent Alpha', 'slug' => 'agent-alpha']);
    $agentB = Agent::factory()->create(['name' => 'Agent Beta', 'slug' => 'agent-beta']);

    $this->actingAs($this->adminUser);

    Livewire::test('pages::admin.agents.index')
        ->call('manageAgent', $agentB->id)
        ->assertRedirect(route('dashboard'));

    expect(session('admin_impersonated_agent_id'))->toBe($agentB->id)
        ->and($this->adminUser->currentAgent()->id)->toBe($agentB->id);
});
