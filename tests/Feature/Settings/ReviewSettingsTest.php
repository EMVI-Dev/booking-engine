<?php

use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Models\Operator;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
                ['id' => 'ChIJsunrise1234567890'],
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
        ->set('review_url', 'https://g.page/r/sunrise-excursions')
        ->call('updateReviewSettings')
        ->assertHasNoErrors();

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
        ->set('google_place_query', 'Sunrise Reef Tours Jimbaran')
        ->assertHasNoErrors()
        ->assertSet('googlePlacePreview.name', 'Sunrise Reef Tours')
        ->assertSee('Connect this listing?')
        ->call('promptConnectGooglePlace')
        ->assertDispatched('open-modal', 'confirm-google-listing')
        ->call('confirmGooglePlace')
        ->assertHasNoErrors();

    $settings = $this->operator->fresh()->settings;

    expect($settings['google_place']['place_id'])->toBe('ChIJsunrise1234567890')
        ->and($settings['google_place']['reviews'][0]['text'])->toBe('Clear water and a kind crew.')
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
