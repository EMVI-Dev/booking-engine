<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Plan;
use App\Services\GooglePlacesService;
use Illuminate\Support\Facades\Http;

function openGoogleReviewsShop(Operator $operator): void
{
    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => $operator->slug.'.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $operator->update([
        'bio' => 'Day trips around the reef.',
        'contact_whatsapp' => '+628111222333',
        'terms_and_conditions' => 'Free cancel until the day before.',
        'bank_provider' => 'BCA',
        'bank_account_name' => $operator->name,
        'bank_account_number' => '1234567890',
        'billing_email' => 'finance@'.$operator->slug.'.test',
        'booking_notification_email' => 'bookings@'.$operator->slug.'.test',
    ]);
}

test('the booking page shows the free Google card and review buttons, with no Places request', function () {
    Plan::seedDefaultPlans();
    config(['services.google.places_key' => 'server-key', 'services.google.maps_embed_key' => 'public-embed-key']);
    Http::fake();

    $operator = Operator::factory()->incompleteSetup()->create([
        'plan_id' => Plan::where('slug', 'agency')->value('id'),
        'name' => 'Sunrise Reef Tours',
        'slug' => 'sunrise-reef',
        'status' => OperatorStatus::Approved,
        'settings' => ['google_place' => ['place_id' => 'ChIJsunrise1234567890']],
    ]);

    openGoogleReviewsShop($operator);

    $this->get('http://sunrise-reef.booking.test/')
        ->assertOk()
        ->assertSee('Reviews on Google')
        ->assertSee('https://www.google.com/maps/embed/v1/place?key=public-embed-key&amp;q=place_id:ChIJsunrise1234567890', false)
        ->assertSee('Read our reviews on Google')
        ->assertSee('query_place_id=ChIJsunrise1234567890', false)
        ->assertSee('Leave a review')
        ->assertSee('https://search.google.com/local/writereview?placeid=ChIJsunrise1234567890', false)
        ->assertDontSee('server-key');

    // Everything shown is free: no Places API call is made for a page view.
    Http::assertNothingSent();
});

test('without an embed key the shop still shows the free review buttons', function () {
    Plan::seedDefaultPlans();
    config(['services.google.maps_embed_key' => null]);

    $operator = Operator::factory()->incompleteSetup()->create([
        'plan_id' => Plan::where('slug', 'agency')->value('id'),
        'slug' => 'reef-nokey',
        'status' => OperatorStatus::Approved,
        'settings' => ['google_place' => ['place_id' => 'ChIJsunrise1234567890']],
    ]);

    openGoogleReviewsShop($operator);

    $this->get('http://reef-nokey.booking.test/')
        ->assertOk()
        ->assertSee('Leave a review')
        ->assertDontSee('maps/embed', false);
});

test('only the place id is ever stored for a Google listing', function () {
    Plan::seedDefaultPlans();
    Http::fake();

    $operator = Operator::factory()->create([
        'plan_id' => Plan::where('slug', 'agency')->value('id'),
    ]);

    app(GooglePlacesService::class)->connect($operator, 'ChIJsunrise1234567890');

    expect(array_keys($operator->fresh()->settings['google_place']))->toBe(['place_id', 'connected_at']);
});

test('the monthly check strips old stored content and disconnects ids Google no longer has', function () {
    Plan::seedDefaultPlans();
    config(['services.google.places_key' => 'test-places-key']);

    $kept = Operator::factory()->create([
        'plan_id' => Plan::where('slug', 'agency')->value('id'),
        'settings' => ['google_place' => ['place_id' => 'ChIJkeep000000000001', 'name' => 'Old copy', 'reviews' => [['text' => 'stored']]]],
    ]);
    $gone = Operator::factory()->create([
        'plan_id' => Plan::where('slug', 'agency')->value('id'),
        'settings' => ['google_place' => ['place_id' => 'ChIJgone000000000002']],
    ]);

    Http::fake([
        'places.googleapis.com/v1/places/ChIJkeep000000000001' => Http::response(['id' => 'ChIJkeep000000000001']),
        'places.googleapis.com/v1/places/ChIJgone000000000002' => Http::response(['error' => ['status' => 'NOT_FOUND']], 404),
    ]);

    $this->artisan('google:check-listings')->assertSuccessful();

    expect($kept->fresh()->settings['google_place'])->toHaveKeys(['place_id', 'connected_at'])
        ->and($kept->fresh()->settings['google_place'])->not->toHaveKey('reviews')
        ->and($gone->fresh()->googlePlaceId())->toBeNull();
});

test('the booking page hides the slider when Google is not connected', function () {
    $operator = Operator::factory()->incompleteSetup()->create([
        'name' => 'Quiet Reef Tours',
        'slug' => 'quiet-reef',
        'status' => OperatorStatus::Approved,
    ]);

    openGoogleReviewsShop($operator);

    $this->get('http://quiet-reef.booking.test/')
        ->assertOk()
        ->assertDontSee('Reviews from Google')
        ->assertDontSee('Clear water and a kind crew.');
});
