<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\User;

test('platform admin is not served on an operator slug', function () {
    $operator = Operator::factory()->create([
        'slug' => 'bali-trek',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'bali-trek.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $admin = User::factory()->create(['is_admin' => true]);

    $this->get('http://bali-trek.booking.test/admin/login', ['Host' => 'bali-trek.booking.test'])
        ->assertNotFound()
        ->assertDontSee('Admin sign in');

    $this->get('http://bali-trek.booking.test/admin', ['Host' => 'bali-trek.booking.test'])
        ->assertNotFound()
        ->assertDontSee('Admin sign in');

    $this->actingAs($admin)
        ->get('http://bali-trek.booking.test/admin', ['Host' => 'bali-trek.booking.test'])
        ->assertNotFound();
});

test('platform admin is not served on an unknown slug host', function () {
    $this->get('http://no-such-operator.booking.test/admin/login', ['Host' => 'no-such-operator.booking.test'])
        ->assertNotFound()
        ->assertDontSee('Admin sign in');
});

test('platform admin remains available on the platform host', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Admin sign in');
});
