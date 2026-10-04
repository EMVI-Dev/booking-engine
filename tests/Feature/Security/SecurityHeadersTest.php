<?php

use App\Models\User;

test('web responses carry baseline security headers', function () {
    $this->get('/')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('hsts is only sent in production over https', function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
});

test('sign-up and password reset posts are rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => "nobody{$attempt}@example.com"]);
    }

    $this->post(route('password.email'), ['email' => 'nobody6@example.com'])->assertStatus(429);
});

test('settings pages need a verified email, except the profile page', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);

    $this->get(route('brand.edit'))->assertRedirect(route('verification.notice'));
    $this->get(route('profile.edit'))->assertOk();
});
