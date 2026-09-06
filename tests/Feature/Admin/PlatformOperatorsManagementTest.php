<?php

use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->create([
        'email' => 'admin@emvi.dev',
        'is_admin' => true,
    ]);

    $this->plan = Plan::create([
        'name' => 'Pro',
        'slug' => 'pro-operator',
        'price_monthly' => 499000,
        'price_yearly' => 4990000,
        'commission_rate' => 0.05,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->operator = Operator::factory()->create([
        'name' => 'Komodo Island Tours',
        'slug' => 'komodo-tours',
        'status' => OperatorStatus::Approved,
        'plan_id' => $this->plan->id,
        'contact_whatsapp' => '+628123456789',
        'bank_provider' => 'BCA',
        'bank_account_number' => '882910293',
        'bank_account_name' => 'PT Komodo Tours',
    ]);

    Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Komodo 3D2N Sailing',
    ]);

    Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'GoPro Rental',
        'sellable_standalone' => true,
    ]);
});

test('admin can view operators directory with rich metrics', function () {
    $this->actingAs($this->adminUser)
        ->get(route('admin.operators.index'))
        ->assertOk()
        ->assertSee('Operators')
        ->assertSee('Komodo Island Tours')
        ->assertSee('komodo-tours')
        ->assertSee('Pro')
        ->assertSee('BCA')
        ->assertSee('+628123456789');
});

test('admin can filter operators by status and search keyword', function () {
    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.operators.index')
        ->set('search', 'Komodo')
        ->assertSee('Komodo Island Tours')
        ->set('status_filter', 'suspended')
        ->assertSee('No matching operators found');
});

test('admin can update operator status and trigger modal', function () {
    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.operators.index')
        ->call('confirmOperatorStatus', $this->operator->id, 'suspended')
        ->assertSet('showConfirmOperatorStatusModal', true)
        ->assertSet('statusActionOperatorId', $this->operator->id)
        ->call('executeOperatorStatus')
        ->assertSet('showConfirmOperatorStatusModal', false);

    expect($this->operator->fresh()->status)->toBe(OperatorStatus::Suspended);
});

test('admin can view operator details and analytics page', function () {
    $this->actingAs($this->adminUser)
        ->get(route('admin.operators.show', $this->operator->id))
        ->assertOk()
        ->assertSee('Komodo Island Tours')
        ->assertSee('Komodo 3D2N Sailing')
        ->assertSee('GoPro Rental');
});

test('admin can switch to operator portal from operators management', function () {
    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.operators.index')
        ->call('manageOperator', $this->operator->id)
        ->assertRedirect(route('dashboard'));

    expect(session('admin_impersonated_operator_id'))->toBe($this->operator->id);
});

test('admin can hold and give back booking money from the operator page', function () {
    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Komodo Sunset Cruise',
    ]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $package->id,
        'guest_name' => 'Sari Dispute',
    ]);

    Payment::factory()->create([
        'reservation_id' => $reservation->id,
        'amount' => 1500000,
        'status' => PaymentStatus::Paid,
    ]);

    WalletTransaction::create([
        'operator_id' => $this->operator->id,
        'reservation_id' => $reservation->id,
        'type' => WalletTransactionType::BookingEarning,
        'gross_amount' => 1500000.00,
        'fee_amount' => 0.00,
        'net_amount' => 1500000.00,
        'status' => WalletTransactionStatus::Cleared,
        'description' => 'Cleared earnings',
    ]);

    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.operators.show', ['operator' => $this->operator])
        ->assertSee('Hold money')
        ->assertSee('Sari Dispute')
        ->call('holdBookingMoney', $reservation->id)
        ->assertHasNoErrors()
        ->assertSee('Give money back')
        ->assertSee('Money held until the card fight is finished.')
        ->call('releaseBookingMoney', $reservation->id)
        ->assertHasNoErrors()
        ->assertSee('Hold money')
        ->assertSee('Held money is back in the operator wallet.');

    expect($this->operator->fresh()->getAvailableBalance())->toBe(1500000.00)
        ->and(WalletTransaction::query()->where('reservation_id', $reservation->id)->where('type', WalletTransactionType::DisputeHold)->count())->toBe(1)
        ->and(WalletTransaction::query()->where('reservation_id', $reservation->id)->where('type', WalletTransactionType::DisputeRelease)->count())->toBe(1);
});
