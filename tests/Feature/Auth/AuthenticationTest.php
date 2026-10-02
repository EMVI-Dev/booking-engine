<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertDontSee('Platform Admin')
        ->assertDontSee('admin@travelengine.id');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('platform admins cannot use the operator login', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])
        ->assertSessionHasErrors([
            'email' => __('This sign-in is for tour operators. Admins use the admin sign-in page.'),
        ]);

    $this->assertGuest();
});

test('operators can authenticate on their slug host', function () {
    Cache::flush();

    $operator = Operator::factory()->create([
        'slug' => 'blue-reef',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'blue-reef.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $owner = User::factory()->create();
    $operator->users()->attach($owner->id, ['role' => OperatorUserRole::Owner]);

    $this->get('http://blue-reef.booking.test/login', ['Host' => 'blue-reef.booking.test'])
        ->assertOk()
        ->assertSee('action="http://blue-reef.booking.test/login"', false)
        ->assertDontSee(__('Demo operator'))
        ->assertDontSee('Platform Admin');

    $this->post('http://blue-reef.booking.test/login', [
        'email' => $owner->email,
        'password' => 'password',
    ], ['Host' => 'blue-reef.booking.test'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($owner);
});

test('operators cannot authenticate on another shop login', function () {
    Cache::flush();

    $shop = Operator::factory()->create([
        'slug' => 'blue-reef',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $shop->id,
        'domain' => 'blue-reef.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $owner = User::factory()->create();
    $shop->users()->attach($owner->id, ['role' => OperatorUserRole::Owner]);

    $rival = Operator::factory()->create([
        'status' => OperatorStatus::Approved,
    ]);
    $rivalUser = User::factory()->create();
    $rival->users()->attach($rivalUser->id, ['role' => OperatorUserRole::Owner]);

    $this->from('http://blue-reef.booking.test/login')
        ->post('http://blue-reef.booking.test/login', [
            'email' => $rivalUser->email,
            'password' => 'password',
        ], ['Host' => 'blue-reef.booking.test'])
        ->assertSessionHasErrors([
            'email' => __('These credentials do not match this tour shop.'),
        ]);

    $this->assertGuest();
});

test('operators logging in on platform host are redirected to their own slug desk', function () {
    Cache::flush();

    $operator = Operator::factory()->create([
        'slug' => 'blue-reef',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => 'blue-reef.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $owner = User::factory()->create();
    $operator->users()->attach($owner->id, ['role' => OperatorUserRole::Owner]);

    $response = $this->post(route('login.store'), [
        'email' => $owner->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toBeString()
        ->and($location)->toContain('://blue-reef.')
        ->and($location)->toContain('/auth/login-handoff');

    $parts = parse_url($location);
    $handoffPath = ($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');

    $this->withServerVariables([
        'HTTP_HOST' => $parts['host'] ?? 'blue-reef.booking.test',
    ])->get($handoffPath)
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($owner);
});

test('operator login handoff preserves remember me', function () {
    Cache::flush();

    $operator = Operator::factory()->create([
        'slug' => 'coral-bay',
        'status' => OperatorStatus::Approved,
    ]);

    $owner = User::factory()->create();
    $operator->users()->attach($owner->id, ['role' => OperatorUserRole::Owner]);

    $response = $this->post(route('login.store'), [
        'email' => $owner->email,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toContain('remember=1');

    $parts = parse_url($location);
    $handoffPath = ($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');

    $this->withServerVariables([
        'HTTP_HOST' => $parts['host'] ?? 'coral-bay.booking.test',
    ])->get($handoffPath)
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($owner);
});

test('tampered login handoff url is rejected', function () {
    $operator = Operator::factory()->create(['slug' => 'coral-bay']);
    $owner = User::factory()->create();
    $operator->users()->attach($owner->id, ['role' => OperatorUserRole::Owner]);

    $response = $this->get('/auth/login-handoff?user='.$owner->id.'&signature=invalid');
    $response->assertForbidden();
});
