<?php

use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'name' => 'Paradise Expeditions',
        'status' => OperatorStatus::Approved,
        'bank_provider' => 'BCA',
        'bank_account_name' => 'Original Owner',
        'bank_account_number' => '1234567890',
        'bank_account_ref' => 'BCA - 1234567890 (Original Owner)',
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => OperatorUserRole::Owner]);
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

    $this->operator->refresh();

    expect($this->operator->bank_provider)->toBe('BCA')
        ->and($this->operator->bank_account_name)->toBe('PT Paradise Expeditions')
        ->and($this->operator->bank_account_number)->toBe('1234567890')
        ->and($this->operator->settings['payment_gateway']['provider'])->toBe('doku')
        ->and($this->operator->settings['payment_gateway']['use_custom_credentials'])->toBeFalse();
});

test('payment settings can be updated with custom BYO merchant gateway', function () {
    $enterprisePlan = Plan::factory()->create([
        'slug' => 'enterprise',
        'name' => 'Agency Ultimate',
        'features' => ['byo_gateway' => true],
    ]);
    $this->operator->update(['plan_id' => $enterprisePlan->id]);

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

    $this->operator->refresh();

    expect($this->operator->bank_provider)->toBe('Mandiri')
        ->and($this->operator->bank_account_name)->toBe('PT Paradise Expeditions')
        ->and($this->operator->bank_account_number)->toBe('9876543210')
        ->and($this->operator->bank_account_ref)->toBe('Mandiri - 9876543210 (PT Paradise Expeditions)')
        ->and($this->operator->settings['payment_gateway']['provider'])->toBe('midtrans')
        ->and($this->operator->settings['payment_gateway']['use_custom_credentials'])->toBeTrue()
        ->and($this->operator->settings['payment_gateway']['environment'])->toBe('production')
        ->and($this->operator->settings['payment_gateway']['client_id'])->toBe('MID-CLIENT-999')
        ->and($this->operator->settings['payment_gateway']['shared_key'])->toBe('MID-SERVER-KEY-888');
});
