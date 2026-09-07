<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\CapacityUnavailableException;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\Product;
use App\Models\Reservation;
use App\Services\CapacityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();

    $this->operator = Operator::factory()->create([
        'slug' => 'bali-sea',
        'status' => OperatorStatus::Approved,
        'terms_and_conditions' => 'Standard tour terms apply.',
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'bali-sea.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);

    $this->product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Snorkel Day Pass',
        'capacity_per_day' => 10,
        'price' => 250000.00,
        'advance_booking_hours' => 0,
        'sellable_standalone' => true,
        'status' => ListingStatus::Published,
    ]);

    $this->date = now()->addDays(5)->format('Y-m-d');
    $this->capacity = app(CapacityService::class);
});

/**
 * Place a reservation directly, bypassing the checkout component.
 */
function bookSeats(Operator $operator, Product|Package $bookable, string $date, int $pax, ReservationStatus $status = ReservationStatus::Confirmed, ?string $holdExpiresAt = null): Reservation
{
    return Reservation::factory()->create([
        'operator_id' => $operator->id,
        'bookable_type' => $bookable instanceof Package ? 'package' : 'product',
        'bookable_id' => $bookable->id,
        'requested_date' => $date,
        'pax_count' => $pax,
        'status' => $status,
        'hold_expires_at' => $holdExpiresAt,
    ]);
}

test('remaining capacity subtracts confirmed bookings for the date', function () {
    expect($this->capacity->remainingCapacity($this->product, $this->date))->toBe(10);

    bookSeats($this->operator, $this->product, $this->date, 4);

    expect($this->capacity->remainingCapacity($this->product, $this->date))->toBe(6);
});

test('capacity is tracked per date rather than globally', function () {
    bookSeats($this->operator, $this->product, $this->date, 10);

    $otherDate = now()->addDays(6)->format('Y-m-d');

    expect($this->capacity->remainingCapacity($this->product, $this->date))->toBe(0)
        ->and($this->capacity->remainingCapacity($this->product, $otherDate))->toBe(10);
});

test('live unpaid holds consume capacity but expired holds release it', function () {
    bookSeats($this->operator, $this->product, $this->date, 6, ReservationStatus::PaymentPending, now()->addMinutes(20)->toDateTimeString());

    expect($this->capacity->remainingCapacity($this->product, $this->date))->toBe(4);

    bookSeats($this->operator, $this->product, $this->date, 3, ReservationStatus::PaymentPending, now()->subMinute()->toDateTimeString());

    expect($this->capacity->remainingCapacity($this->product, $this->date))->toBe(4);
});

test('cancelled, declined and expired bookings do not hold seats', function () {
    foreach ([ReservationStatus::Cancelled, ReservationStatus::Declined, ReservationStatus::Expired] as $status) {
        bookSeats($this->operator, $this->product, $this->date, 3, $status);
    }

    expect($this->capacity->remainingCapacity($this->product, $this->date))->toBe(10);
});

test('a package draws down the daily capacity of every activity it bundles', function () {
    $boat = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Speedboat Seat',
        'capacity_per_day' => 12,
        'status' => ListingStatus::Published,
    ]);

    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'status' => ListingStatus::Published,
    ]);

    $package->products()->sync([
        $this->product->id => ['quantity_required' => 1],
        $boat->id => ['quantity_required' => 2],
    ]);

    $package->load('products');

    // The boat is the binding constraint: 12 seats / 2 per guest = 6 guests
    expect($this->capacity->remainingCapacity($package->fresh()->load('products'), $this->date))->toBe(6);

    // Selling the package must also reduce standalone availability of its activities
    bookSeats($this->operator, $package, $this->date, 4);

    Model::preventLazyLoading();

    try {
        expect($this->capacity->remainingCapacity($this->product->fresh(), $this->date))->toBe(6)
            ->and($this->capacity->remainingCapacity($boat->fresh(), $this->date))->toBe(4);
    } finally {
        Model::preventLazyLoading(false);
    }
});

test('standalone activity bookings reduce availability of packages that bundle them', function () {
    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'status' => ListingStatus::Published,
    ]);

    $package->products()->sync([$this->product->id => ['quantity_required' => 1]]);

    bookSeats($this->operator, $this->product, $this->date, 8);

    expect($this->capacity->remainingCapacity($package->fresh()->load('products'), $this->date))->toBe(2);
});

test('items with no underlying activity are treated as uncapped', function () {
    $emptyPackage = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'status' => ListingStatus::Published,
    ]);

    expect($this->capacity->remainingCapacity($emptyPackage->load('products'), $this->date))->toBeNull();
});

test('reserve throws once the requested party exceeds what is left', function () {
    bookSeats($this->operator, $this->product, $this->date, 8);

    expect(fn () => $this->capacity->reserve($this->product, $this->date, 3, fn () => 'created'))
        ->toThrow(CapacityUnavailableException::class);

    expect($this->capacity->reserve($this->product, $this->date, 2, fn () => 'created'))->toBe('created');
});

test('guest checkout refuses to oversell the last remaining seats', function () {
    bookSeats($this->operator, $this->product, $this->date, 9);

    Livewire::test('storefront.booking-box', [
        'bookable' => $this->product,
        'operator' => $this->operator,
    ])
        ->set('requested_date', $this->date)
        ->set('pax_count', 2)
        ->set('guest_name', 'Sarah Connor')
        ->set('guest_contact', '081234567890')
        ->set('agreed_terms', true)
        ->call('submitBooking')
        ->assertHasErrors('requested_date');

    expect(Reservation::where('bookable_id', $this->product->id)->count())->toBe(1);
});

test('sequential checkouts cannot push a date past its daily capacity', function () {
    $book = fn (int $pax) => Livewire::test('storefront.booking-box', [
        'bookable' => $this->product,
        'operator' => $this->operator,
    ])
        ->set('requested_date', $this->date)
        ->set('pax_count', $pax)
        ->set('guest_name', 'Guest')
        ->set('guest_contact', '081234567890')
        ->set('agreed_terms', true)
        ->call('submitBooking');

    $book(6)->assertHasNoErrors();
    $book(4)->assertHasNoErrors();
    $book(1)->assertHasErrors('requested_date');

    $totalPax = Reservation::where('bookable_id', $this->product->id)->sum('pax_count');

    expect($totalPax)->toBe(10)
        ->and($this->capacity->remainingCapacity($this->product, $this->date))->toBe(0);
});

test('the booking box reports remaining availability to the guest', function () {
    bookSeats($this->operator, $this->product, $this->date, 7);

    Livewire::test('storefront.booking-box', [
        'bookable' => $this->product,
        'operator' => $this->operator,
    ])
        ->set('requested_date', $this->date)
        ->assertSee('Only 3 spots left for this date');
});

test('a sold out date disables the checkout button', function () {
    bookSeats($this->operator, $this->product, $this->date, 10);

    Livewire::test('storefront.booking-box', [
        'bookable' => $this->product,
        'operator' => $this->operator,
    ])
        ->set('requested_date', $this->date)
        ->assertSee('Sold Out on This Date')
        ->assertSee('Fully booked on this date');
});

test('the packages catalog paginates instead of loading every listing', function () {
    Package::factory()->count(15)->create([
        'operator_id' => $this->operator->id,
        'status' => ListingStatus::Published,
    ]);

    $headers = ['Host' => 'bali-sea.booking.test'];

    $firstPage = $this->get('http://bali-sea.booking.test/tours', $headers);
    $firstPage->assertOk()
        ->assertSee('15 Experiences Available')
        ->assertSee('page=2');

    $this->get('http://bali-sea.booking.test/tours?page=2', $headers)->assertOk();
});
