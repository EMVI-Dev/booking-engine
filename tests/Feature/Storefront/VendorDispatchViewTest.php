<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\OperatorStatus;
use App\Enums\ReservationStatus;
use App\Mail\VendorBookingNotificationMail;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->operator = Operator::factory()->create([
        'name' => 'Sunset Bali Tours',
        'slug' => 'sunset-bali',
        'status' => OperatorStatus::Approved,
        'booking_notification_email' => 'contact@sunsetbali.com',
        'contact_whatsapp' => '+62812345678',
    ]);

    OperatorDomain::factory()->create([
        'operator_id' => $this->operator->id,
        'domain' => 'sunset-bali.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
        'is_primary' => true,
    ]);

    $this->vendor = Vendor::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Ubud Quad Adventures',
        'reservation_email' => 'booking@ubudquad.com',
    ]);

    $this->product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'vendor_id' => $this->vendor->id,
        'name' => 'Single ATV Jungle Tour',
        'price' => 750000.00,
    ]);

    $this->reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Product::class,
        'bookable_id' => $this->product->id,
        'guest_name' => 'David Copperfield',
        'guest_email' => 'david@magic.com',
        'guest_contact' => '+62899112233',
        'pax_count' => 3,
        'status' => ReservationStatus::Confirmed,
    ]);
});

test('vendor can view dispatch sheet with the vendor token', function () {
    $url = "http://sunset-bali.booking.test/vendor-dispatch/{$this->reservation->vendor_token}";

    $response = $this->get($url, ['Host' => 'sunset-bali.booking.test']);

    $response->assertOk()
        ->assertViewIs('storefront.vendor-dispatch')
        ->assertSee('Sunset Bali Tours')
        ->assertSee('Single ATV Jungle Tour')
        ->assertSee('David Copperfield')
        ->assertSee('+62899112233')
        ->assertSee('3 Guests')
        ->assertSee('contact@sunsetbali.com')
        ->assertDontSee('750000'); // Customer retail price hidden
});

test('the vendor sheet does not open with the guest token or a wrong token', function () {
    $this->get("http://sunset-bali.booking.test/vendor-dispatch/{$this->reservation->public_token}", ['Host' => 'sunset-bali.booking.test'])->assertNotFound();
    $this->get('http://sunset-bali.booking.test/vendor-dispatch/invalid_token_123', ['Host' => 'sunset-bali.booking.test'])->assertNotFound();
});

test('vendor emails carry the vendor token, never the guest token', function () {
    $html = (new VendorBookingNotificationMail($this->reservation, $this->vendor, [$this->product]))->render();

    expect($this->reservation->vendor_token)->not->toBe($this->reservation->public_token)
        ->and($html)->toContain($this->reservation->vendor_token)
        ->and($html)->not->toContain($this->reservation->public_token);
});

test('guest lookup on find-booking redirects to e-ticket', function () {
    $response = $this->post('http://sunset-bali.booking.test/find-booking', [
        'code' => $this->reservation->code,
        'contact' => 'david@magic.com',
    ], ['Host' => 'sunset-bali.booking.test']);

    $response->assertRedirect(route('storefront.reservation.ticket', $this->reservation));
});
