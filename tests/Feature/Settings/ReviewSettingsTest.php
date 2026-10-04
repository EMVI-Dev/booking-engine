<?php

use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use App\Services\GooglePlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'name' => 'Original Agency',
        'status' => OperatorStatus::Approved,
    ]);
    $this->operator->users()->attach($this->user->id, ['role' => OperatorUserRole::Owner]);
    $this->actingAs($this->user);
});

function putReviewOperatorOnAgencyPlan(Operator $operator): void
{
    Plan::seedDefaultPlans();
    $operator->update([
        'plan_id' => Plan::where('slug', 'agency')->value('id'),
    ]);
}

function fakeReviewGooglePlaceLookup(): void
{
    config(['services.google.places_key' => 'test-places-key']);

    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response([
            'places' => [
                ['id' => 'ChIJsunrise1234567890', 'displayName' => ['text' => 'Sunrise Reef Tours'], 'formattedAddress' => 'Jimbaran, Bali'],
            ],
        ]),
        'places.googleapis.com/v1/places/*' => Http::response([
            'id' => 'ChIJsunrise1234567890',
            'displayName' => ['text' => 'Sunrise Reef Tours'],
            'formattedAddress' => 'Jimbaran, Bali',
            'rating' => 4.8,
            'userRatingCount' => 42,
            'googleMapsUri' => 'https://maps.google.com/?cid=1',
            'reviews' => [
                [
                    'rating' => 5,
                    'text' => ['text' => 'Clear water and a kind crew.'],
                    'relativePublishTimeDescription' => '2 weeks ago',
                    'googleMapsUri' => 'https://maps.google.com/review/1',
                    'authorAttribution' => ['displayName' => 'Ayu'],
                ],
            ],
        ]),
    ]);
}

test('reviews settings live on their own storefront tab', function () {
    $this->get(route('review-settings.edit'))
        ->assertOk()
        ->assertSee('Reviews')
        ->assertSee('Review Platform (e.g. Google/Tripadvisor)')
        ->assertDontSee('Find listing');

    $this->get(route('brand.edit'))
        ->assertOk()
        ->assertSee('Reviews')
        ->assertDontSee('Review Platform (e.g. Google/Tripadvisor)');
});

test('an operator can save a review platform url from the reviews tab', function () {
    Livewire::test('pages::settings.reviews')
        ->assertSee('Finish the highlighted fields')
        ->assertSee('Needed for bookings')
        ->assertSeeHtml('id="setup-reviews"')
        ->assertSeeHtml('data-setup-needed="true"')
        ->set('review_url', 'https://g.page/r/sunrise-excursions')
        ->call('updateReviewSettings')
        ->assertHasNoErrors()
        ->assertDispatched('setup-progress-updated')
        ->assertDontSee('Finish the highlighted fields')
        ->assertDontSeeHtml('data-setup-needed="true"');

    expect($this->operator->fresh()->getReviewUrl())->toBe('https://g.page/r/sunrise-excursions');
});

test('an agency operator without a listing can choose a review link', function () {
    putReviewOperatorOnAgencyPlan($this->operator);

    Livewire::test('pages::settings.reviews')
        ->assertSee('Connect a Google listing')
        ->assertSee('Use a review link')
        ->assertDontSee('Review Platform (e.g. Google/Tripadvisor)')
        ->call('chooseReviewSource', 'link')
        ->assertSet('reviewSource', 'link')
        ->assertSee('Review Platform (e.g. Google/Tripadvisor)')
        ->set('review_url', 'https://www.tripadvisor.com/Attraction_Review-sunrise')
        ->call('updateReviewSettings')
        ->assertHasNoErrors();

    expect($this->operator->fresh()->getReviewUrl())
        ->toBe('https://www.tripadvisor.com/Attraction_Review-sunrise')
        ->and($this->operator->fresh()->reviewInvitationUrl())
        ->toBe('https://www.tripadvisor.com/Attraction_Review-sunrise')
        ->and($this->operator->fresh()->googlePlaceId())->toBeNull();
});

test('an operator can connect a Google listing without overwriting the review platform url', function () {
    putReviewOperatorOnAgencyPlan($this->operator);
    fakeReviewGooglePlaceLookup();

    $this->operator->update([
        'settings' => array_merge($this->operator->settings ?? [], [
            'marketing' => [
                'review_url' => 'https://g.page/r/keep-this',
            ],
        ]),
    ]);

    Livewire::test('pages::settings.reviews')
        ->assertDontSee('Press Enter to show the listing.')
        ->call('chooseReviewSource', 'listing')
        ->set('google_place_query', 'ChIJsunrise1234567890')
        ->assertHasNoErrors()
        ->assertSet('googlePlacePreviewId', 'ChIJsunrise1234567890')
        ->assertSee('Connect this listing?')
        ->call('promptConnectGooglePlace')
        ->assertDispatched('open-modal', 'confirm-google-listing')
        ->call('confirmGooglePlace')
        ->assertHasNoErrors();

    $settings = $this->operator->fresh()->settings;

    expect($settings['google_place']['place_id'])->toBe('ChIJsunrise1234567890')
        ->and($settings['google_place'])->not->toHaveKey('reviews')
        ->and($this->operator->fresh()->getReviewUrl())->toBe('https://g.page/r/keep-this');

    Livewire::test('pages::settings.reviews')
        ->assertDontSee('Press Enter to show the listing.')
        ->assertDontSee('Use a review link')
        ->assertSee('Disconnect');
});

test('connecting Google does not copy the listing into the review platform url', function () {
    putReviewOperatorOnAgencyPlan($this->operator);
    fakeReviewGooglePlaceLookup();

    Livewire::test('pages::settings.reviews')
        ->call('chooseReviewSource', 'listing')
        ->set('google_place_query', 'https://maps.google.com/?place_id=ChIJsunrise1234567890')
        ->call('promptConnectGooglePlace')
        ->assertDispatched('open-modal', 'confirm-google-listing')
        ->call('confirmGooglePlace');

    expect($this->operator->fresh()->getReviewUrl())->toBeNull()
        ->and($this->operator->fresh()->reviewInvitationUrl())
        ->toBe('https://search.google.com/local/writereview?placeid=ChIJsunrise1234567890')
        ->and($this->operator->fresh()->googlePlaceId())->toBe('ChIJsunrise1234567890');
});

test('disconnecting Google clears the cached listing and asks again', function () {
    putReviewOperatorOnAgencyPlan($this->operator);

    $this->operator->update([
        'settings' => array_merge($this->operator->settings ?? [], [
            'google_place' => [
                'place_id' => 'ChIJsunrise1234567890',
                'name' => 'Sunrise Reef Tours',
                'fetched_at' => now()->toIso8601String(),
                'reviews' => [],
            ],
            'marketing' => [
                'review_url' => 'https://g.page/r/keep-this',
            ],
        ]),
    ]);

    Livewire::test('pages::settings.reviews')
        ->assertSee('Disconnect this listing?')
        ->assertDontSee('Use a review link')
        ->call('promptDisconnectGooglePlace')
        ->assertDispatched('open-modal', 'confirm-disconnect-google-listing')
        ->call('disconnectGooglePlace')
        ->assertSee('Use a review link');

    expect($this->operator->fresh()->googlePlaceId())->toBeNull()
        ->and($this->operator->fresh()->getReviewUrl())->toBe('https://g.page/r/keep-this');
});

test('a business name is not searched; the operator is asked for the place id', function () {
    putReviewOperatorOnAgencyPlan($this->operator);
    fakeReviewGooglePlaceLookup();

    Livewire::test('pages::settings.reviews')
        ->call('chooseReviewSource', 'listing')
        ->assertSee('How to find your Place ID')
        ->assertSee(GooglePlacesService::PLACE_ID_FINDER_URL)
        ->set('google_place_query', 'EMVI')
        ->assertHasErrors(['google_place_query'])
        ->assertSet('googlePlacePreviewId', null);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'places:searchText'));
});

test('connecting a listing stores only its place id', function () {
    putReviewOperatorOnAgencyPlan($this->operator);
    fakeReviewGooglePlaceLookup();

    Livewire::test('pages::settings.reviews')
        ->call('chooseReviewSource', 'listing')
        ->set('google_place_query', 'ChIJsunrise1234567890')
        ->assertSet('googlePlacePreviewId', 'ChIJsunrise1234567890')
        ->call('confirmGooglePlace')
        ->assertHasNoErrors();

    expect(array_keys($this->operator->fresh()->settings['google_place']))->toBe(['place_id', 'connected_at']);
});

test('a pasted place id is checked with the free id-only request, never a billed search', function () {
    putReviewOperatorOnAgencyPlan($this->operator);
    fakeReviewGooglePlaceLookup();

    Livewire::test('pages::settings.reviews')
        ->call('chooseReviewSource', 'listing')
        ->set('google_place_query', 'Place ID: ChIJsunrise1234567890')
        ->assertSet('googlePlacePreviewId', 'ChIJsunrise1234567890')
        ->assertSee('Open on Google Maps');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-FieldMask', 'id'));
});

test('an unknown place id is refused', function () {
    putReviewOperatorOnAgencyPlan($this->operator);
    config(['services.google.places_key' => 'test-places-key']);
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND']], 404)]);

    Livewire::test('pages::settings.reviews')
        ->call('chooseReviewSource', 'listing')
        ->set('google_place_query', 'ChIJdoesNotExist0000000')
        ->assertHasErrors(['google_place_query'])
        ->assertSet('googlePlacePreviewId', null);
});

test('the reviews settings page never calls the billed Google details', function () {
    putReviewOperatorOnAgencyPlan($this->operator);
    fakeReviewGooglePlaceLookup();
    $this->operator->update(['settings' => array_merge($this->operator->settings ?? [], ['google_place' => ['place_id' => 'ChIJsunrise1234567890']])]);

    Livewire::test('pages::settings.reviews')
        ->assertSee('Google listing connected')
        ->assertSee('ChIJsunrise1234567890')
        ->assertSee('View on Google Maps');

    Http::assertNothingSent();
});

test('the confirmed place id cannot be swapped from the browser', function () {
    putReviewOperatorOnAgencyPlan($this->operator);

    expect(fn () => Livewire::test('pages::settings.reviews')
        ->set('googlePlacePreviewId', 'ChIJfake1234567890'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('after a valid place id the operator sees Google\'s free card to check the business', function () {
    putReviewOperatorOnAgencyPlan($this->operator);
    fakeReviewGooglePlaceLookup();
    config(['services.google.maps_embed_key' => 'public-embed-key']);

    Livewire::test('pages::settings.reviews')
        ->call('chooseReviewSource', 'listing')
        ->set('google_place_query', 'ChIJsunrise1234567890')
        ->assertSeeHtml('https://www.google.com/maps/embed/v1/place?key=public-embed-key&amp;q=place_id:ChIJsunrise1234567890');
});
