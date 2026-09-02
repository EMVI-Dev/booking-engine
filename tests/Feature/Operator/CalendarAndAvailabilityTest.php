<?php

use App\Models\AvailabilityBlock;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Services\DokuPaymentService;
use App\Services\WhatsAppDispatchService;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->operator = Operator::factory()->create();
    $this->operator->users()->attach($this->user->id, ['role' => 'owner']);
});

test('unauthenticated user cannot access calendar page', function () {
    $this->get(route('calendar.index'))
        ->assertRedirect(route('login'));
});

test('operator can view calendar with month days and scheduled reservations', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id]);

    $tripDate = now()->addDays(3)->format('Y-m-d');

    Reservation::factory()->confirmed()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => 'package',
        'bookable_id' => $package->id,
        'requested_date' => $tripDate,
        'guest_name' => 'Michael Scott',
        'pax_count' => 4,
    ]);

    $this->get(route('calendar.index'))
        ->assertOk()
        ->assertSee('Booking Calendar &amp; Availability', false);

    Livewire::test('calendar.month-grid')
        ->assertSee(now()->format('F Y'))
        ->call('selectDate', $tripDate)
        ->assertSee('Michael Scott')
        ->assertSee('4 Pax');
});

test('operator can navigate calendar months', function () {
    $this->actingAs($this->user);

    $nextMonthLabel = now()->startOfMonth()->addMonth()->format('F Y');
    $prevMonthLabel = now()->startOfMonth()->subMonth()->format('F Y');

    Livewire::test('calendar.month-grid')
        ->call('nextMonth')
        ->assertSee($nextMonthLabel)
        ->call('prevMonth')
        ->call('prevMonth')
        ->assertSee($prevMonthLabel)
        ->call('currentMonth')
        ->assertSee(now()->format('F Y'));
});

test('operator can create operator-wide blackout blocks', function () {
    $this->actingAs($this->user);

    Livewire::test('calendar.month-grid')
        ->set('blockTargetType', 'all')
        ->set('blockStartDate', now()->addDays(5)->format('Y-m-d'))
        ->set('blockEndDate', now()->addDays(7)->format('Y-m-d'))
        ->set('blockReason', 'National Holiday Break')
        ->call('saveBlackoutBlock')
        ->assertHasNoErrors()
        ->assertSee('Blackout block saved successfully.');

    $block = AvailabilityBlock::where('operator_id', $this->operator->id)->first();

    expect($block)->not->toBeNull()
        ->and($block->reason)->toBe('National Holiday Break')
        ->and($block->product_id)->toBeNull()
        ->and($block->package_id)->toBeNull()
        ->and($block->isOperatorWide())->toBeTrue();
});

test('operator can create package-specific blackout blocks', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create(['operator_id' => $this->operator->id, 'title' => 'Sunset Cruise']);

    Livewire::test('calendar.month-grid')
        ->set('blockTargetType', 'package')
        ->set('blockPackageIds', [$package->id])
        ->set('blockStartDate', now()->addDays(10)->format('Y-m-d'))
        ->set('blockEndDate', now()->addDays(12)->format('Y-m-d'))
        ->set('blockReason', 'Private Charter Booking')
        ->call('saveBlackoutBlock')
        ->assertHasNoErrors();

    $block = AvailabilityBlock::where('operator_id', $this->operator->id)->where('package_id', $package->id)->first();

    expect($block)->not->toBeNull()
        ->and($block->isPackageSpecific())->toBeTrue()
        ->and($block->getTargetLabel())->toContain('Sunset Cruise');

    expect($package->isBlackedOutOn(now()->addDays(11)->toDateString()))->toBeTrue();
    expect($package->isBlackedOutOn(now()->addDays(20)->toDateString()))->toBeFalse();
});

test('operator can create product-specific blackout blocks and delete them', function () {
    $this->actingAs($this->user);

    $product = Product::factory()->create(['operator_id' => $this->operator->id, 'name' => 'Snorkeling Session']);

    Livewire::test('calendar.month-grid')
        ->set('blockTargetType', 'product')
        ->set('blockProductIds', [$product->id])
        ->set('blockStartDate', now()->addDays(10)->format('Y-m-d'))
        ->set('blockEndDate', now()->addDays(12)->format('Y-m-d'))
        ->set('blockReason', 'Equipment Maintenance')
        ->call('saveBlackoutBlock')
        ->assertHasNoErrors()
        ->assertSee('Blackout block saved successfully.');

    $block = AvailabilityBlock::where('operator_id', $this->operator->id)->first();

    expect($block)->not->toBeNull()
        ->and($block->reason)->toBe('Equipment Maintenance')
        ->and($block->product_id)->toBe($product->id)
        ->and($block->getTargetLabel())->toContain('Snorkeling Session');

    expect($product->isBlackedOutOn(now()->addDays(11)->toDateString()))->toBeTrue();

    Livewire::test('calendar.month-grid')
        ->call('confirmDeleteBlackoutBlock', $block->id)
        ->assertDispatched('open-modal', 'confirm-blackout-removal')
        ->call('deleteBlackoutBlock')
        ->assertDispatched('close-modal', 'confirm-blackout-removal')
        ->assertSee('Blackout block removed.');

    expect(AvailabilityBlock::count())->toBe(0);
    expect($product->isBlackedOutOn(now()->addDays(11)->toDateString()))->toBeFalse();
});

test('calendar month grid displays blackout marker on blacked out date cells', function () {
    $this->actingAs($this->user);

    $blackoutDate = now()->startOfMonth()->addDays(2)->toDateString();

    AvailabilityBlock::create([
        'operator_id' => $this->operator->id,
        'product_id' => null,
        'package_id' => null,
        'date_start' => $blackoutDate,
        'date_end' => $blackoutDate,
        'reason' => 'All Operator Maintenance',
    ]);

    $test = Livewire::test('calendar.month-grid');
    $days = $test->get('calendarDays');

    $cell = collect($days)->firstWhere('date', $blackoutDate);

    expect($cell)->not->toBeNull()
        ->and($cell['hasBlock'])->toBeTrue()
        ->and($cell['isOperatorWideBlock'])->toBeTrue();

    $test->assertSee('Blackout (All)');
});

test('package blackout detects underlying product blackout', function () {
    $product = Product::factory()->create(['operator_id' => $this->operator->id]);
    $package = Package::factory()->create(['operator_id' => $this->operator->id]);
    $package->products()->attach($product->id, ['quantity_required' => 1]);

    $blackoutDate = now()->addDays(8)->toDateString();

    AvailabilityBlock::create([
        'operator_id' => $this->operator->id,
        'product_id' => $product->id,
        'package_id' => null,
        'date_start' => $blackoutDate,
        'date_end' => $blackoutDate,
        'reason' => 'Product Maintenance',
    ]);

    expect($package->isBlackedOutOn($blackoutDate))->toBeTrue();
});

test('storefront booking box blocks checkout on blackout date', function () {
    $product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'price' => 250000,
        'sellable_standalone' => true,
        'advance_booking_hours' => 0,
    ]);

    $blackoutDate = now()->addDays(4)->toDateString();

    AvailabilityBlock::create([
        'operator_id' => $this->operator->id,
        'product_id' => $product->id,
        'date_start' => $blackoutDate,
        'date_end' => $blackoutDate,
        'reason' => 'Scheduled Maintenance',
    ]);

    Livewire::test('storefront.booking-box', ['bookable' => $product, 'operator' => $this->operator])
        ->set('requested_date', $blackoutDate)
        ->set('guest_name', 'Jane Doe')
        ->set('guest_contact', '08123456789')
        ->set('agreed_terms', true)
        ->call('submitBooking', app(DokuPaymentService::class))
        ->assertHasErrors(['requested_date']);
});

test('operator booking link creation rejects blacked out date', function () {
    $this->actingAs($this->user);

    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'price' => 500000,
        'status' => 'published',
    ]);

    $blackoutDate = now()->addDays(5)->toDateString();

    AvailabilityBlock::create([
        'operator_id' => $this->operator->id,
        'package_id' => $package->id,
        'date_start' => $blackoutDate,
        'date_end' => $blackoutDate,
        'reason' => 'Private Event',
    ]);

    Livewire::test('pages::reservations.index')
        ->set('createExperienceSelection', "package:{$package->id}")
        ->set('createRequestedDate', $blackoutDate)
        ->set('createPaxCount', 2)
        ->set('createGuestName', 'John Doe')
        ->set('createGuestContact', '081298765432')
        ->call('generateBookingLink', app(DokuPaymentService::class), app(WhatsAppDispatchService::class))
        ->assertHasErrors(['createRequestedDate']);
});
