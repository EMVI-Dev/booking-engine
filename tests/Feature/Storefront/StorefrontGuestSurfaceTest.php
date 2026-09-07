<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\ReservationStatus;
use App\Mail\GuestBookingConfirmedMail;
use App\Mail\GuestBookingCreatedMail;
use App\Mail\GuestDepartureReminderMail;
use App\Mail\GuestReviewRequestMail;
use App\Mail\OperatorNewBookingNotificationMail;
use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Reservation;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    Plan::seedDefaultPlans();

    $this->operator = Operator::factory()->create([
        'name' => 'Whitebox Reef Tours',
        'slug' => 'whitebox-reef',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::where('slug', 'starter')->value('id'),
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'whitebox-reef.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Sunrise Reef Walk',
        'slug' => 'sunrise-reef-walk',
        'status' => ListingStatus::Published,
    ]);

    $this->product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Mask and Fin Hire',
        'slug' => 'mask-and-fin-hire',
        'sellable_standalone' => true,
        'status' => ListingStatus::Published,
    ]);

    $this->headers = ['Host' => 'whitebox-reef.booking.test'];
    $this->host = 'http://whitebox-reef.booking.test';
});

test('guest catalog pages show only this operator published listings', function () {
    Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Draft Hidden Night Dive',
        'status' => ListingStatus::Draft,
    ]);

    $rival = Operator::factory()->create([
        'name' => 'Rival Boats',
        'slug' => 'rival-boats',
        'status' => OperatorStatus::Approved,
    ]);

    Package::factory()->create([
        'operator_id' => $rival->id,
        'title' => 'Rival Secret Cruise',
        'slug' => 'rival-secret-cruise',
        'status' => ListingStatus::Published,
    ]);

    Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Crew-Only Transfer',
        'slug' => 'crew-only-transfer',
        'sellable_standalone' => false,
        'status' => ListingStatus::Published,
    ]);

    $this->get($this->host.'/', $this->headers)
        ->assertOk()
        ->assertSee('Whitebox Reef Tours')
        ->assertSee('Sunrise Reef Walk')
        ->assertSee('Mask and Fin Hire')
        ->assertDontSee('Draft Hidden Night Dive')
        ->assertDontSee('Rival Secret Cruise')
        ->assertDontSee('Crew-Only Transfer');

    $this->get($this->host.'/tours', $this->headers)
        ->assertOk()
        ->assertSee('Sunrise Reef Walk')
        ->assertDontSee('Draft Hidden Night Dive')
        ->assertDontSee('Rival Secret Cruise');

    $this->get($this->host.'/services', $this->headers)
        ->assertOk()
        ->assertSee('Mask and Fin Hire')
        ->assertDontSee('Crew-Only Transfer');
});

test('unpublished or foreign listings are not reachable by slug', function () {
    $draft = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'slug' => 'draft-hidden-night-dive',
        'status' => ListingStatus::Draft,
    ]);

    $rival = Operator::factory()->create(['status' => OperatorStatus::Approved]);
    $foreign = Package::factory()->create([
        'operator_id' => $rival->id,
        'slug' => 'rival-secret-cruise',
        'status' => ListingStatus::Published,
    ]);

    Product::factory()->create([
        'operator_id' => $this->operator->id,
        'slug' => 'crew-only-transfer',
        'sellable_standalone' => false,
        'status' => ListingStatus::Published,
    ]);

    $this->get($this->host.'/packages/'.$draft->slug, $this->headers)->assertNotFound();
    $this->get($this->host.'/packages/'.$foreign->slug, $this->headers)->assertNotFound();
    $this->get($this->host.'/products/crew-only-transfer', $this->headers)->assertNotFound();

    $this->get($this->host.'/packages/'.$this->package->slug, $this->headers)
        ->assertOk()
        ->assertSee('Sunrise Reef Walk')
        ->assertSeeLivewire('storefront.booking-box');

    $this->get($this->host.'/products/'.$this->product->slug, $this->headers)
        ->assertOk()
        ->assertSee('Mask and Fin Hire')
        ->assertSeeLivewire('storefront.booking-box');
});

test('terms and find-booking are on the public storefront', function () {
    $this->get($this->host.'/terms', $this->headers)
        ->assertOk()
        ->assertSee('Whitebox Reef Tours');

    $this->get($this->host.'/find-booking', $this->headers)
        ->assertOk()
        ->assertSee('Find your booking');
});

test('starter storefront credits the platform in the footer and share site name', function () {
    $platform = config('app.name');

    $this->get($this->host.'/', $this->headers)
        ->assertOk()
        ->assertSee('Powered by')
        ->assertSee($this->operator->name.' • '.$platform, false)
        ->assertDontSee('WebApplication', false)
        ->assertDontSee('#platform', false);
});

test('agency storefront does not credit the platform in titles, share cards, or footer', function () {
    $this->operator->update(['plan_id' => Plan::where('slug', 'agency')->value('id')]);
    $platform = config('app.name');

    $this->get($this->host.'/', $this->headers)
        ->assertOk()
        ->assertDontSee('Powered by')
        ->assertDontSee('name="generator"', false)
        ->assertDontSee('&bull; '.$platform, false)
        ->assertDontSee($this->operator->name.' • '.$platform, false)
        ->assertDontSee('WebApplication', false)
        ->assertDontSee('#platform', false)
        ->assertSee($this->operator->name);

    $this->get($this->host.'/tours', $this->headers)
        ->assertOk()
        ->assertDontSee('&bull; '.$platform, false);

    $this->get($this->host.'/packages/'.$this->package->slug, $this->headers)
        ->assertOk()
        ->assertDontSee('&bull; '.$platform, false);
});

test('agency guest mail and calendar file do not credit the platform', function () {
    $this->operator->update(['plan_id' => Plan::where('slug', 'agency')->value('id')]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'guest_email' => 'guest@example.com',
        'status' => ReservationStatus::Confirmed,
    ]);

    Payment::factory()->paid()->create([
        'reservation_id' => $reservation->id,
        'amount' => 750000,
    ]);

    $mail = new GuestReviewRequestMail($reservation, 'https://example.test/review');
    $html = $mail->render();

    expect($html)->toContain('Sent by Whitebox Reef Tours')
        ->and($html)->not->toContain('via '.config('app.name'));

    $this->get($this->host.'/reservations/'.$reservation->public_token.'/receipt', $this->headers)
        ->assertOk()
        ->assertDontSee('PRODID:-//'.config('app.name'), false);
});

test('agency booking mail uses the operator as the from name', function (string $mailable) {
    $this->operator->update([
        'plan_id' => Plan::where('slug', 'agency')->value('id'),
        'booking_notification_email' => 'bookings@whitebox.test',
    ]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::Confirmed,
    ]);

    $mail = $mailable === GuestReviewRequestMail::class
        ? new GuestReviewRequestMail($reservation, 'https://example.test/review')
        : new $mailable($reservation);

    $envelope = $mail->envelope();

    expect($envelope->from?->name)->toBe('Whitebox Reef Tours')
        ->and($envelope->from?->address)->toBe(config('mail.from.address'))
        ->and($envelope->from?->name)->not->toBe(config('app.name'));

    if ($mailable === OperatorNewBookingNotificationMail::class) {
        expect($envelope->replyTo)->toBeEmpty();
    } else {
        expect($envelope->replyTo[0]->address)->toBe('bookings@whitebox.test')
            ->and($envelope->replyTo[0]->name)->toBe('Whitebox Reef Tours');
    }
})->with([
    GuestBookingCreatedMail::class,
    GuestBookingConfirmedMail::class,
    GuestDepartureReminderMail::class,
    GuestReviewRequestMail::class,
    OperatorNewBookingNotificationMail::class,
]);

test('starter booking mail still uses the platform as the from name', function () {
    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::Confirmed,
    ]);

    $envelope = (new GuestBookingConfirmedMail($reservation))->envelope();

    expect($envelope->from?->name)->toBe((string) (config('mail.from.name') ?: config('app.name')))
        ->and($envelope->replyTo[0]->address)->toBe($this->operator->booking_notification_email);
});

test('subscription reminder mail stays on the platform from name', function () {
    $this->operator->update(['plan_id' => Plan::where('slug', 'agency')->value('id')]);

    $mail = new SubscriptionRenewalReminderMail(
        $this->operator->fresh(),
        Plan::where('slug', 'agency')->firstOrFail(),
        3,
    );

    expect($mail->envelope()->from)->toBeNull();
});
