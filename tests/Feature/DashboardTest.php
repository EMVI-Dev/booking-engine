<?php

use App\Models\Operator;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('new operators see a welcome toast cue and clickable setup steps', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create([
        'name' => 'First Week Tours',
        'bio' => null,
        'contact_whatsapp' => null,
        'terms_and_conditions' => null,
        'bank_provider' => null,
        'bank_account_number' => null,
        'bank_account_name' => null,
        'bank_account_ref' => null,
    ]);
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user)
        ->withSession(['welcome_onboarding' => true])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Finish setup to take bookings')
        ->assertSee('Guests cannot pay you until these are done.')
        ->assertSee('Continue')
        ->assertSee('Page is closed')
        ->assertSee('Add an activity')
        ->assertSee('Payout bank account')
        ->assertSee(route('brand.edit', absolute: false))
        ->assertSee(route('payments.edit', absolute: false))
        ->assertSee(route('review-settings.edit', absolute: false))
        ->assertSee(route('products.create', absolute: false))
        ->assertSee('Logo')
        ->assertSee('Hero banner copy')
        ->assertSee('Reviews')
        ->assertDontSee('Your account is created');
});

test('setup banner shows one primary next step without duplicating it in the list', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create([
        'name' => 'Almost Ready Tours',
        'bio' => null,
    ]);
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Finish setup to take bookings')
        ->assertSee('4 of 9 done')
        ->assertSee('Short bio')
        ->assertSee('Logo')
        ->assertSee('Hero banner copy')
        ->assertSee('Reviews')
        ->assertSee('Add an activity');

    $html = $response->getContent();

    expect(substr_count($html, 'Continue'))->toBe(1)
        ->and($html)->not->toMatch('/Continue<\/span>\s*<span class="opacity-60"[^>]*>·<\/span>\s*<span>Add an activity/');
});

test('setup banner refreshes immediately when setup progress is updated', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->incompleteSetup()->create([
        'name' => 'Live Banner Tours',
        'contact_whatsapp' => '+628123456789',
    ]);
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user);

    $banner = Livewire::test('operator-setup-banner')
        ->assertSee('Finish setup to take bookings')
        ->assertSee('0 of 9 done');

    $operator->update([
        'bio' => 'Morning snorkel trips from Sanur.',
        'terms_and_conditions' => 'Cancel 24 hours before departure.',
        'bank_provider' => 'BCA',
        'bank_account_name' => 'Live Banner Tours',
        'bank_account_number' => '1234567890',
        'billing_email' => 'billing@livebanner.test',
        'booking_notification_email' => 'bookings@livebanner.test',
        'logo_path' => 'operators/live-banner/brand/logo.webp',
        'settings' => array_merge($operator->settings ?? [], [
            'storefront' => [
                'hero_headline' => 'Live Banner Tours',
                'hero_tagline' => 'Snorkel mornings in Sanur',
            ],
            'marketing' => [
                'review_url' => 'https://example.com/reviews',
            ],
        ]),
    ]);

    $banner->dispatch('setup-progress-updated')
        ->assertSee('One step left before guests can book')
        ->assertSee('8 of 9 done')
        ->assertSee('Add an activity')
        ->assertDontSee('Short bio');
});

test('setup banner with one step left shows only the primary button', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create([
        'name' => 'One Step Tours',
        'logo_path' => 'operators/one-step/brand/logo.webp',
        'settings' => [
            'sellable_standalone_default' => true,
            'brand_color' => '#0f172a',
            'display_name' => 'One Step Tours',
            'storefront' => [
                'hero_headline' => 'One Step Tours',
                'hero_tagline' => 'Ready when you are',
            ],
            'marketing' => [
                'review_url' => 'https://example.com/reviews',
            ],
        ],
    ]);
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('One step left before guests can book')
        ->assertSee('8 of 9 done')
        ->assertSee('Add an activity')
        ->assertDontSee('Short bio')
        ->assertDontSee('more after this');

    expect(substr_count($response->getContent(), 'Add an activity'))->toBe(1);
});

test('operator dashboard uses quiet chrome and yellow primary actions', function () {
    $user = User::factory()->create();
    $operator = Operator::factory()->create(['name' => 'Quiet Chrome Tours']);
    $operator->users()->attach($user->id, ['role' => 'owner']);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Quiet Chrome Tours')
        ->assertSee(__('Operator Portal'))
        ->assertSee(__('Create Booking Link'))
        ->assertSee(__('Live Storefront'))
        ->assertSee('op-shell')
        ->assertSee('op-palette-ebony')
        ->assertSee('op-hero')
        ->assertSee('op-metric-featured')
        ->assertSee('op-metric')
        ->assertSee('shadow-none')
        ->assertSee('op-nav-item')
        ->assertSee('op-nav-icon')
        ->assertSee('op-sidebar')
        ->assertSee('w-72')
        ->assertSee('op-card')
        ->assertSee('Bookings')
        ->assertSee('Wallet')
        ->assertSee(__('Create package'))
        ->assertSee(__('Add activity'))
        ->assertSee(__('Upcoming'))
        ->assertSee(__('Recent bookings'))
        ->assertSee(__('Shortcuts'))
        ->assertDontSee('bg-[#FFEF4D] text-[#090d16] font-black shadow-xs', false);
});
