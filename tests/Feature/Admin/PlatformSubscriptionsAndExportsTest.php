<?php

use App\Enums\OperatorStatus;
use App\Enums\PayoutStatus;
use App\Models\Operator;
use App\Models\PayoutRequest;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->create([
        'email' => 'admin@emvi.dev',
        'is_admin' => true,
    ]);

    $this->plan = Plan::create([
        'name' => 'Growth Pro',
        'slug' => 'growth-pro',
        'price_monthly' => 499000,
        'price_yearly' => 4990000,
        'commission_rate' => 0.05,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->operator = Operator::factory()->create([
        'name' => 'Nusa Penida Charters',
        'slug' => 'penida-charters',
        'status' => OperatorStatus::Approved,
        'plan_id' => $this->plan->id,
    ]);
});

test('platform admin can access subscription invoices page', function () {
    SubscriptionPayment::create([
        'operator_id' => $this->operator->id,
        'plan_id' => $this->plan->id,
        'invoice_number' => 'INV-SUB-TEST-99',
        'type' => 'subscription_upgrade',
        'billing_interval' => 'monthly',
        'gross_amount' => 499000,
        'net_amount_paid' => 499000,
        'status' => 'completed',
        'gateway' => 'doku',
        'paid_at' => now(),
    ]);

    $this->actingAs($this->adminUser)
        ->get(route('admin.subscriptions.index'))
        ->assertOk()
        ->assertSee('Subscription Invoices')
        ->assertSee('INV-SUB-TEST-99')
        ->assertSee('Nusa Penida Charters')
        ->assertSee('Growth Pro')
        ->assertSee('Rp 499.000');
});

test('platform admin can export subscription invoices to csv', function () {
    SubscriptionPayment::create([
        'operator_id' => $this->operator->id,
        'plan_id' => $this->plan->id,
        'invoice_number' => 'INV-CSV-01',
        'type' => 'subscription_upgrade',
        'billing_interval' => 'monthly',
        'gross_amount' => 499000,
        'net_amount_paid' => 499000,
        'status' => 'completed',
        'gateway' => 'doku',
        'paid_at' => now(),
    ]);

    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.subscriptions')
        ->call('exportCsv')
        ->assertFileDownloaded('subscription-invoices-'.now()->format('Y-m-d').'.csv');
});

test('platform admin can export operators directory to csv', function () {
    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.operators.index')
        ->call('exportCsv')
        ->assertFileDownloaded('operators-'.now()->format('Y-m-d').'.csv');
});

test('platform admin can export payouts list to csv', function () {
    PayoutRequest::create([
        'reference_number' => 'PAY-CSV-001',
        'operator_id' => $this->operator->id,
        'amount' => 1500000,
        'bank_provider' => 'BCA',
        'bank_account_name' => 'PT Penida Charters',
        'bank_account_number' => '12345678',
        'status' => PayoutStatus::Pending,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.payouts')
        ->call('exportCsv')
        ->assertFileDownloaded('operator-payouts-'.now()->format('Y-m-d').'.csv');
});

test('platform settings page displays system health and queue status', function () {
    $this->actingAs($this->adminUser)
        ->get(route('admin.platform.edit'))
        ->assertOk()
        ->assertSee('System Health & Queues')
        ->assertSee('Database')
        ->assertSee('Cache Driver')
        ->assertSee('Failed Queue Jobs');
});
