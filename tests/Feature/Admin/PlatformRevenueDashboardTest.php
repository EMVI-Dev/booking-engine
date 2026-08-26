<?php

use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Reservation;
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

    $this->enterprisePlan = Plan::create([
        'name' => 'Enterprise Pro',
        'slug' => 'enterprise',
        'price_monthly' => 999000,
        'price_yearly' => 9990000,
        'commission_rate' => 0.02,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->operator = Operator::factory()->create([
        'name' => 'Bali Ocean Adventures',
        'slug' => 'bali-ocean',
        'status' => OperatorStatus::Approved,
        'plan_id' => $this->enterprisePlan->id,
        'subscribed_at' => now()->subMonths(3),
    ]);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $package->id,
        'status' => ReservationStatus::Confirmed,
    ]);

    Payment::factory()->create([
        'reservation_id' => $reservation->id,
        'amount' => 5000000,
        'status' => PaymentStatus::Paid,
        'gateway' => 'doku',
        'created_at' => now(),
    ]);

    SubscriptionPayment::create([
        'operator_id' => $this->operator->id,
        'plan_id' => $this->enterprisePlan->id,
        'invoice_number' => 'INV-SUB-001',
        'type' => 'subscription_upgrade',
        'billing_interval' => 'monthly',
        'gross_amount' => 999000,
        'net_amount_paid' => 999000,
        'status' => 'completed',
        'gateway' => 'doku',
        'paid_at' => now(),
    ]);
});

test('admin can access platform revenue dashboard', function () {
    $this->actingAs($this->adminUser)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Platform Revenue & Financial Intelligence')
        ->assertSee('Platform Gross GMV')
        ->assertSee('Subscription MRR');
});

test('revenue dashboard computes financial metrics accurately', function () {
    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.dashboard')
        ->assertSet('period', '12m')
        ->assertSee('Bali Ocean Adventures')
        ->assertSee('Enterprise Pro')
        ->assertSee('Rp 5.000.000');
});
