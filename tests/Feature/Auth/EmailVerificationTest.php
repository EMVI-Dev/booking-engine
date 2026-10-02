<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertNotDispatched(Verified::class);
});

test('new operator sign-ups must verify their email and the link opens on their desk host', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Ketut Guide',
        'email' => 'ketut@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'agency_name' => 'Ketut Treks',
        'slug' => 'ketut-treks',
        'terms' => '1',
    ]);

    $user = User::where('email', 'ketut@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse();

    $link = null;
    Notification::assertSentTo($user, VerifyEmail::class, function ($notification) use ($user, &$link) {
        $link = $notification->toMail($user)->actionUrl;

        return true;
    });

    expect(parse_url($link, PHP_URL_HOST))->toStartWith('ketut-treks.');

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));

    $this->actingAs($user)->get($link)->assertRedirect();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('setting a password from an emailed reset link verifies the address', function () {
    $user = User::factory()->unverified()->create();
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewSecurePass123!',
        'password_confirmation' => 'NewSecurePass123!',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
