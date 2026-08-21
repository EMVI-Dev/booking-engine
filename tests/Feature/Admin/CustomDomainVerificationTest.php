<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();

    $this->admin = User::factory()->create([
        'is_admin' => true,
        'email' => 'admin-dns@emvi.dev',
    ]);

    $this->operator = Operator::factory()->create([
        'name' => 'Komodo Luxury Charters',
        'slug' => 'komodo-luxury',
        'status' => OperatorStatus::Approved,
    ]);

    $this->customDomain = OperatorDomain::create([
        'operator_id' => $this->operator->id,
        'domain' => 'komodoluxury.com',
        'type' => DomainType::Custom,
        'status' => DomainStatus::Pending,
    ]);
});

test('admin can view custom domains and DNS manager', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.domains.index'));

    $response->assertOk()
        ->assertSee('Custom Domains & DNS Manager')
        ->assertSee('komodoluxury.com')
        ->assertSee('Komodo Luxury Charters');
});

test('admin can check DNS and activate custom domain', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.domains')
        ->call('checkDns', $this->customDomain->id)
        ->call('activateDomain', $this->customDomain->id)
        ->assertHasNoErrors();

    $this->customDomain->refresh();
    expect($this->customDomain->status)->toBe(DomainStatus::Active)
        ->and($this->customDomain->verified_at)->not->toBeNull()
        ->and($this->customDomain->ssl_issued_at)->not->toBeNull();
});

test('admin can toggle domain status to pending or failed', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.domains')
        ->call('setDomainStatus', $this->customDomain->id, 'pending')
        ->assertHasNoErrors();

    $this->customDomain->refresh();
    expect($this->customDomain->status)->toBe(DomainStatus::Pending);
});
