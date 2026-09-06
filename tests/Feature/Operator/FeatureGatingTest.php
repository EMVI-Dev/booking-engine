<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\OperatorUser;
use App\Models\Package;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Plan::seedDefaultPlans();

    $this->user = User::factory()->create();

    $this->starterPlan = Plan::where('slug', 'starter')->first();
    $this->growthPlan = Plan::where('slug', 'growth')->first();
    $this->agencyPlan = Plan::where('slug', 'agency')->first();

    $this->operator = Operator::factory()->create([
        'name' => 'Nusa Cruising Co',
        'slug' => 'nusa-cruising',
        'status' => OperatorStatus::Approved,
        'plan_id' => $this->starterPlan->id,
        'terms_and_conditions' => 'Standard tour terms apply.',
        'bank_provider' => 'BCA',
        'bank_account_number' => '1234567890',
        'contact_whatsapp' => '081234567890',
    ]);

    OperatorUser::create([
        'operator_id' => $this->operator->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);
});

test('starter plan operator sees feature gate on guest directory crm', function () {
    $response = $this->actingAs($this->user)->get(route('guests.index'));

    $response->assertOk()
        ->assertSee('Requires Growth Plan')
        ->assertSee('Guest Directory CRM &amp; Lifetime Tracking', false);
});

test('growth plan operator has full access to guest directory crm', function () {
    $this->operator->update(['plan_id' => $this->growthPlan->id]);

    $response = $this->actingAs($this->user)->get(route('guests.index'));

    $response->assertOk()
        ->assertDontSee('Requires Growth Plan')
        ->assertSee('Guest Directory &amp; CRM', false);
});

test('starter plan operator cannot exceed package limit of 5', function () {
    // Create 5 packages
    Package::factory()->count(5)->create([
        'operator_id' => $this->operator->id,
        'title' => 'Tour Package',
        'status' => 'published',
    ]);

    expect($this->operator->canAddPackage())->toBeFalse();

    // Starter operator sees feature gate on package create
    $response = $this->actingAs($this->user)->get(route('packages.create'));
    $response->assertOk()
        ->assertSee('Package Limit Reached (5 Listings)')
        ->assertSee('Requires Growth Plan');
});

test('growth plan operator can have up to 25 packages', function () {
    $this->operator->update(['plan_id' => $this->growthPlan->id]);

    Package::factory()->count(5)->create([
        'operator_id' => $this->operator->id,
        'title' => 'Tour Package',
        'status' => 'published',
    ]);

    expect($this->operator->canAddPackage())->toBeTrue();
});

test('agency plan operator has unlimited packages', function () {
    $this->operator->update(['plan_id' => $this->agencyPlan->id]);

    Package::factory()->count(30)->create([
        'operator_id' => $this->operator->id,
        'title' => 'Tour Package',
        'status' => 'published',
    ]);

    expect($this->operator->canAddPackage())->toBeTrue();
});

test('starter plan gates tracking pixels and automated review requests while growth unlocks them', function () {
    expect($this->starterPlan->hasFeature('quick_booking_links'))->toBeTrue()
        ->and($this->starterPlan->hasFeature('tracking_pixels'))->toBeFalse()
        ->and($this->starterPlan->hasFeature('automated_review_requests'))->toBeFalse()
        ->and($this->growthPlan->hasFeature('tracking_pixels'))->toBeTrue()
        ->and($this->growthPlan->hasFeature('automated_review_requests'))->toBeTrue()
        ->and($this->agencyPlan->hasFeature('tracking_pixels'))->toBeTrue()
        ->and($this->agencyPlan->hasFeature('automated_review_requests'))->toBeTrue();

    // Starter operator sees upgrade banner on brand settings
    $response = $this->actingAs($this->user)->get(route('brand.edit'));
    $response->assertOk()
        ->assertSee('Requires Growth or Agency');

    // Upgrade to growth
    $this->operator->update(['plan_id' => $this->growthPlan->id]);
    $response = $this->actingAs($this->user)->get(route('brand.edit'));
    $response->assertOk()
        ->assertDontSee('Requires Growth or Agency')
        ->assertSee('Active &amp; Unlocked', false);
});

/**
 * Hiding a control in Blade is not authorization: Livewire actions and components
 * stay reachable by name, so each gated feature must also refuse server-side.
 */
test('starter plan operator cannot mutate the guest crm by calling the action directly', function () {
    $guest = Guest::factory()->create(['operator_id' => $this->operator->id]);

    Livewire::actingAs($this->user)
        ->test('pages::guests.index')
        ->set('selectedGuestId', $guest->id)
        ->set('editName', 'Renamed Guest')
        ->call('saveGuest')
        ->assertForbidden();

    expect($guest->fresh()->name)->not->toBe('Renamed Guest');
});

test('starter plan operator cannot mount gated calendar components directly', function () {
    Livewire::actingAs($this->user)->test('calendar.resource-timeline')->assertForbidden();
    Livewire::actingAs($this->user)->test('calendar.capacity-heatmap')->assertForbidden();
    Livewire::actingAs($this->user)->test('calendar.daily-manifest')->assertForbidden();
});

test('growth plan operator may mount the calendar components its plan includes', function () {
    $this->operator->update(['plan_id' => $this->growthPlan->id]);

    Livewire::actingAs($this->user)->test('calendar.resource-timeline')->assertOk();
    Livewire::actingAs($this->user)->test('calendar.daily-manifest')->assertOk();
    Livewire::actingAs($this->user)->test('calendar.capacity-heatmap')->assertForbidden();
});

test('starter plan operator cannot save tracking pixels through brand settings', function () {
    Livewire::actingAs($this->user)
        ->test('pages::settings.brand')
        ->set('meta_pixel_id', '1234567890')
        ->set('google_analytics_id', 'G-SNEAKY')
        ->call('updateBrandSettings')
        ->assertHasNoErrors();

    $tracking = $this->operator->fresh()->settings['tracking'] ?? [];

    expect($tracking['meta_pixel_id'] ?? null)->toBeNull()
        ->and($tracking['google_analytics_id'] ?? null)->toBeNull();
});

test('growth plan operator can save tracking pixels', function () {
    $this->operator->update(['plan_id' => $this->growthPlan->id]);

    Livewire::actingAs($this->user)
        ->test('pages::settings.brand')
        ->set('meta_pixel_id', '1234567890')
        ->call('updateBrandSettings')
        ->assertHasNoErrors();

    expect($this->operator->fresh()->settings['tracking']['meta_pixel_id'] ?? null)->toBe('1234567890');
});

test('starter listings cap is shared between packages and activities', function () {
    Package::factory()->count(3)->create([
        'operator_id' => $this->operator->id,
        'status' => ListingStatus::Published,
    ]);
    Product::factory()->count(2)->create([
        'operator_id' => $this->operator->id,
        'status' => ListingStatus::Published,
    ]);

    expect($this->operator->listingCount())->toBe(5)
        ->and($this->operator->canAddPackage())->toBeFalse()
        ->and($this->operator->canAddProduct())->toBeFalse();

    Livewire::actingAs($this->user)
        ->test('pages::products.create')
        ->set('name', 'Extra Snorkel')
        ->set('capacity_per_day', 8)
        ->set('sellable_standalone', true)
        ->set('price', 150000)
        ->call('save')
        ->assertHasErrors(['profile']);

    expect(Product::query()->where('name', 'Extra Snorkel')->exists())->toBeFalse();
});

test('starter plan allows the owner plus one helper', function () {
    expect($this->starterPlan->team_member_limit)->toBe(2)
        ->and($this->operator->canAddTeamMember())->toBeTrue();

    $helper = User::factory()->create();
    $this->operator->users()->attach($helper->id, ['role' => 'reservation']);

    expect($this->operator->fresh()->canAddTeamMember())->toBeFalse();
});

test('agency plan hides the platform name on the public booking page', function () {
    OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'nusa-cruising.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->get('http://nusa-cruising.booking.test/', ['Host' => 'nusa-cruising.booking.test'])
        ->assertOk()
        ->assertSee('Powered by');

    $this->operator->update(['plan_id' => $this->agencyPlan->id]);

    $this->get('http://nusa-cruising.booking.test/', ['Host' => 'nusa-cruising.booking.test'])
        ->assertOk()
        ->assertDontSee('Powered by');
});

test('starter plan does not build a whatsapp send link when creating a booking', function () {
    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'price' => 500000,
        'status' => ListingStatus::Published,
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::reservations.index')
        ->call('openCreateLinkModal')
        ->set('createExperienceSelection', "package:{$package->id}")
        ->set('createRequestedDate', now()->addDays(2)->toDateString())
        ->set('createPaxCount', 2)
        ->set('createGuestName', 'Budi Santoso')
        ->set('createGuestContact', '081234567890')
        ->call('generateBookingLink')
        ->assertHasNoErrors()
        ->assertSet('generatedWhatsAppUrl', null)
        ->assertDontSee('Send Payment Link on WhatsApp');
});

test('pro plan builds a whatsapp send link and shows ready-made guest messages', function () {
    $this->operator->update(['plan_id' => $this->growthPlan->id]);

    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'price' => 500000,
        'status' => ListingStatus::Published,
    ]);

    $component = Livewire::actingAs($this->user)
        ->test('pages::reservations.index')
        ->call('openCreateLinkModal')
        ->set('createExperienceSelection', "package:{$package->id}")
        ->set('createRequestedDate', now()->addDays(2)->toDateString())
        ->set('createPaxCount', 2)
        ->set('createGuestName', 'Budi Santoso')
        ->set('createGuestContact', '081234567890')
        ->call('generateBookingLink')
        ->assertHasNoErrors();

    expect($component->get('generatedWhatsAppUrl'))->toContain('wa.me');

    $reservation = Reservation::factory()->create([
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'operator_id' => $this->operator->id,
        'guest_name' => 'Sari Guest',
        'guest_contact' => '081298765432',
        'status' => ReservationStatus::PaymentPending,
        'hold_expires_at' => now()->addMinutes(30),
        'terms_snapshot' => $package->generateTermsSnapshot(),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::reservations.index')
        ->call('viewDetails', $reservation->id)
        ->assertSee('1-Click WhatsApp Guest Dispatch');
});

test('starter plan shows an upgrade note instead of ready-made whatsapp messages', function () {
    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'status' => ListingStatus::Published,
    ]);

    $reservation = Reservation::factory()->create([
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'operator_id' => $this->operator->id,
        'guest_name' => 'Sari Guest',
        'guest_contact' => '081298765432',
        'status' => ReservationStatus::PaymentPending,
        'hold_expires_at' => now()->addMinutes(30),
        'terms_snapshot' => $package->generateTermsSnapshot(),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::reservations.index')
        ->call('viewDetails', $reservation->id)
        ->assertSee('Ready-made WhatsApp messages are on Growth')
        ->assertDontSee('1-Click WhatsApp Guest Dispatch');
});

test('seeded agency plan costs 799000 and includes hide-name and faster help', function () {
    expect((float) $this->agencyPlan->price_monthly)->toBe(799000.00)
        ->and($this->agencyPlan->hasFeature('remove_branding'))->toBeTrue()
        ->and($this->agencyPlan->hasFeature('priority_support'))->toBeTrue()
        ->and($this->starterPlan->hasFeature('remove_branding'))->toBeFalse()
        ->and($this->starterPlan->hasFeature('whatsapp_dispatch'))->toBeFalse();
});

test('seeded public plans are Starter, Growth, and Agency', function () {
    expect($this->starterPlan->name)->toBe('Starter')
        ->and($this->growthPlan->name)->toBe('Growth')
        ->and($this->agencyPlan->name)->toBe('Agency')
        ->and($this->agencyPlan->tierRank())->toBe(3)
        ->and(Plan::where('slug', 'enterprise')->exists())->toBeFalse();
});
