<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\ReservationStatus;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\PlatformSetting;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    config(['fortify.registration_enabled' => true]);

    $this->operator = Operator::factory()->create([
        'name' => 'Quiet Harbor Tours',
        'slug' => 'quiet-harbor',
        'status' => OperatorStatus::Approved,
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'quiet-harbor.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Dawn Reef Drift',
        'price' => 250000.00,
        'status' => ListingStatus::Published,
    ]);

    $this->host = 'http://quiet-harbor.booking.test';
    $this->headers = ['Host' => 'quiet-harbor.booking.test'];
});

test('maintenance blocks operator sign-up but still allows operator log in', function () {
    PlatformSetting::current()->setPlatformMaintenance(true);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Operator sign-up is paused')
        ->assertDontSee('Create account');

    $this->post(route('register.store'), [
        'name' => 'Made Sutrisna',
        'email' => 'made@quietharbor.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'agency_name' => 'Quiet Harbor Tours',
        'terms' => '1',
    ])->assertForbidden();

    expect(User::query()->where('email', 'made@quietharbor.com')->exists())->toBeFalse();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign In to Operator Portal');
});

test('maintenance blocks storefront booking and pay routes', function () {
    PlatformSetting::current()->setPlatformMaintenance(true);

    Livewire::test('storefront.booking-box', [
        'bookable' => $this->package,
        'operator' => $this->operator,
    ])
        ->set('requested_date', now()->addDays(3)->format('Y-m-d'))
        ->set('pax_count', 2)
        ->set('guest_name', 'Sarah Connor')
        ->set('guest_contact', '081987654321')
        ->set('guest_email', 'sarah@example.com')
        ->set('agreed_terms', true)
        ->call('submitBooking')
        ->assertForbidden();

    expect(Reservation::query()->where('guest_name', 'Sarah Connor')->exists())->toBeFalse();

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $this->package->id,
        'status' => ReservationStatus::PaymentPending,
    ]);

    $this->get(route('storefront.reservation.pay', $reservation))->assertForbidden();
    $this->post(route('storefront.reservation.cancel', $reservation))->assertForbidden();
});

test('storefront catalog still loads during maintenance', function () {
    PlatformSetting::current()->setPlatformMaintenance(true);

    $this->get($this->host.'/', $this->headers)
        ->assertOk()
        ->assertSee('Dawn Reef Drift')
        ->assertSee('Bookings and payments are paused');

    $this->get($this->host.'/packages/'.$this->package->slug, $this->headers)
        ->assertOk()
        ->assertSee('Bookings are paused')
        ->assertDontSee('Proceed to Secure Payment');
});
