<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->admin()->create([
        'name' => 'Chief Admin',
        'email' => 'superadmin@travelengine.id',
    ]);
});

test('platform admin can view administrators management page', function () {
    $coAdmin = User::factory()->admin()->create([
        'name' => 'Dev Ops Admin',
        'email' => 'devops@travelengine.id',
    ]);

    $this->actingAs($this->adminUser)
        ->get(route('admin.admins.index'))
        ->assertOk()
        ->assertSee('Platform Administrators')
        ->assertSee('Chief Admin')
        ->assertSee('superadmin@travelengine.id')
        ->assertSee('Dev Ops Admin')
        ->assertSee('devops@travelengine.id');
});

test('non-admin user is rejected from administrators management page', function () {
    $regularUser = User::factory()->create(['is_admin' => false]);

    $this->actingAs($regularUser)
        ->get(route('admin.admins.index'))
        ->assertForbidden();
});

test('platform admin can create a new administrator', function () {
    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.admins')
        ->set('name', 'Security Auditor')
        ->set('email', 'auditor@travelengine.id')
        ->set('password', 'ValidPlatformPassword123!')
        ->set('password_confirmation', 'ValidPlatformPassword123!')
        ->call('createAdministrator')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'auditor@travelengine.id')->first();

    expect($newUser)->not->toBeNull()
        ->and($newUser->name)->toBe('Security Auditor')
        ->and($newUser->is_admin)->toBeTrue()
        ->and($newUser->email_verified_at)->not->toBeNull()
        ->and(Hash::check('ValidPlatformPassword123!', $newUser->password))->toBeTrue();
});

test('create administrator enforces validation and uniqueness', function () {
    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.admins')
        ->set('name', '')
        ->set('email', 'invalid-email')
        ->set('password', 'short')
        ->set('password_confirmation', 'mismatch')
        ->call('createAdministrator')
        ->assertHasErrors(['name', 'email', 'password']);
});

test('admin cannot revoke their own administrator access', function () {
    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.admins')
        ->call('confirmRevokeAdmin', (string) $this->adminUser->id)
        ->assertDispatched('toast', type: 'error');

    expect($this->adminUser->fresh()->is_admin)->toBeTrue();
});

test('admin cannot revoke access when they are the sole administrator', function () {
    $coAdmin = User::factory()->admin()->create();

    // Now 2 admins exist; revoke co-admin
    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.admins')
        ->call('confirmRevokeAdmin', (string) $coAdmin->id)
        ->assertSet('showRevokeModal', true)
        ->call('executeRevokeAdmin');

    expect($coAdmin->fresh()->is_admin)->toBeFalse();

    // Now only 1 admin remains; attempting to revoke fails safeguard
    Livewire::actingAs($this->adminUser)
        ->test('pages::admin.admins')
        ->call('confirmRevokeAdmin', (string) $this->adminUser->id)
        ->assertDispatched('toast', type: 'error');
});
