<?php

use App\Enums\AgentStatus;
use App\Enums\AgentUserRole;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create([
        'name' => 'Paradise Expeditions',
        'status' => AgentStatus::Approved,
        'bank_provider' => 'BCA',
        'bank_account_name' => 'Original Owner',
        'bank_account_number' => '1234567890',
        'bank_account_ref' => 'BCA - 1234567890 (Original Owner)',
    ]);
    $this->agent->users()->attach($this->user->id, ['role' => AgentUserRole::Owner]);
    $this->actingAs($this->user);
});

test('payment settings page is displayed', function () {
    $this->get(route('payments.edit'))->assertOk();
});

test('payment settings can be configured to use built-in platform Doku payment', function () {
    Livewire::test('pages::settings.payments')
        ->set('bank_provider', 'BCA')
        ->set('bank_account_name', 'PT Paradise Expeditions')
        ->set('bank_account_number', '1234567890')
        ->set('payment_mode', 'platform')
        ->call('updatePaymentSettings')
        ->assertHasNoErrors();

    $this->agent->refresh();

    expect($this->agent->bank_provider)->toBe('BCA')
        ->and($this->agent->bank_account_name)->toBe('PT Paradise Expeditions')
        ->and($this->agent->bank_account_number)->toBe('1234567890')
        ->and($this->agent->settings['payment_gateway']['provider'])->toBe('doku')
        ->and($this->agent->settings['payment_gateway']['use_custom_credentials'])->toBeFalse();
});

test('payment settings can be updated with custom BYO merchant gateway', function () {
    Livewire::test('pages::settings.payments')
        ->set('bank_provider', 'Mandiri')
        ->set('bank_account_name', 'PT Paradise Expeditions')
        ->set('bank_account_number', '9876543210')
        ->set('payment_mode', 'custom')
        ->set('selected_gateway_provider', 'midtrans')
        ->set('gateway_environment', 'production')
        ->set('gateway_client_id', 'MID-CLIENT-999')
        ->set('gateway_shared_key', 'MID-SERVER-KEY-888')
        ->call('updatePaymentSettings')
        ->assertHasNoErrors();

    $this->agent->refresh();

    expect($this->agent->bank_provider)->toBe('Mandiri')
        ->and($this->agent->bank_account_name)->toBe('PT Paradise Expeditions')
        ->and($this->agent->bank_account_number)->toBe('9876543210')
        ->and($this->agent->bank_account_ref)->toBe('Mandiri - 9876543210 (PT Paradise Expeditions)')
        ->and($this->agent->settings['payment_gateway']['provider'])->toBe('midtrans')
        ->and($this->agent->settings['payment_gateway']['use_custom_credentials'])->toBeTrue()
        ->and($this->agent->settings['payment_gateway']['environment'])->toBe('production')
        ->and($this->agent->settings['payment_gateway']['client_id'])->toBe('MID-CLIENT-999')
        ->and($this->agent->settings['payment_gateway']['shared_key'])->toBe('MID-SERVER-KEY-888');
});
