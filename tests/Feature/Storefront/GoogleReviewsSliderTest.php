<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Plan;

function googleReviewsSnapshot(): array
{
    return [
        'place_id' => 'ChIJsunrise1234567890',
        'name' => 'Sunrise Reef Tours',
        'address' => 'Jimbaran, Bali',
        'rating' => 4.8,
        'review_count' => 42,
        'maps_url' => 'https://maps.google.com/?cid=1',
        'write_review_url' => 'https://search.google.com/local/writereview?placeid=ChIJsunrise1234567890',
        'reviews' => [
            [
                'author' => 'Ayu',
                'rating' => 5,
                'text' => 'Clear water and a kind crew.',
                'relative_time' => '2 weeks ago',
                'url' => 'https://maps.google.com/review/1',
            ],
        ],
        'fetched_at' => now()->toIso8601String(),
    ];
}

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

test('the booking page shows cached Google reviews as a slider', function () {
    Plan::seedDefaultPlans();

    $operator = Operator::factory()->incompleteSetup()->create([
        'plan_id' => Plan::where('slug', 'agency')->value('id'),
        'name' => 'Sunrise Reef Tours',
        'slug' => 'sunrise-reef',
        'status' => OperatorStatus::Approved,
        'settings' => [
            'google_place' => googleReviewsSnapshot(),
        ],
    ]);

    openGoogleReviewsShop($operator);

    $this->get('http://sunrise-reef.booking.test/')
        ->assertOk()
        ->assertSee('Reviews from Google')
        ->assertSee('Clear water and a kind crew.')
        ->assertSee('Ayu')
        ->assertSee('See on Google')
        ->assertDontSee('Verified reviews');
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
