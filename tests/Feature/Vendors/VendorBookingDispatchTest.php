<?php

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Mail\VendorBookingCancelledMail;
use App\Mail\VendorBookingNotificationMail;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Vendor;
use App\Services\GuestCancellationService;
use App\Services\VendorDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->operator = Operator::factory()->create([
        'name' => 'John Bali Guide',
        'booking_notification_email' => 'john@baliguide.com',
        'contact_whatsapp' => '+628123456789',
    ]);
});

test('vendor receives booking notification email when single product is confirmed', function () {
    Mail::fake();

    $vendor = Vendor::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Bali Quad Bike',
        'reservation_email' => 'booking@baliquad.com',
    ]);

    $product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'vendor_id' => $vendor->id,
        'name' => '2-Hour Jungle ATV',
    ]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Product::class,
        'bookable_id' => $product->id,
        'guest_name' => 'Sarah Jenkins',
        'guest_email' => 'sarah@example.com',
        'guest_contact' => '+61412345678',
        'status' => ReservationStatus::Confirmed,
    ]);

    app(VendorDispatchService::class)->dispatchBookingConfirmation($reservation);

    Mail::assertQueued(VendorBookingNotificationMail::class, function (VendorBookingNotificationMail $mail) use ($vendor) {
        return $mail->hasTo('booking@baliquad.com')
            && $mail->vendor->id === $vendor->id
            && in_array('2-Hour Jungle ATV', $mail->activities, true);
    });
});

test('in-house product without vendor does not dispatch vendor notification email', function () {
    Mail::fake();

    $product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'vendor_id' => null,
        'name' => 'Private Car Tour',
    ]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Product::class,
        'bookable_id' => $product->id,
        'status' => ReservationStatus::Confirmed,
    ]);

    app(VendorDispatchService::class)->dispatchBookingConfirmation($reservation);

    Mail::assertNothingSent();
});

test('package booking with multiple vendors dispatches separate notifications to each vendor', function () {
    Mail::fake();

    $vendorA = Vendor::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Speedboat Fast Ferry',
        'reservation_email' => 'dispatch@fastferry.com',
    ]);

    $vendorB = Vendor::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Coral Scuba Diving',
        'reservation_email' => 'booking@coralscuba.com',
    ]);

    $prod1 = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'vendor_id' => $vendorA->id,
        'name' => 'Return Speedboat Ticket',
    ]);

    $prod2 = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'vendor_id' => $vendorB->id,
        'name' => '2-Dive Scuba Package',
    ]);

    $prod3InHouse = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'vendor_id' => null,
        'name' => 'Guide Transport',
    ]);

    $package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Complete Island Adventure',
    ]);

    $package->products()->attach([$prod1->id, $prod2->id, $prod3InHouse->id]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Package::class,
        'bookable_id' => $package->id,
        'guest_name' => 'Michael Scott',
        'status' => ReservationStatus::Confirmed,
    ]);

    app(VendorDispatchService::class)->dispatchBookingConfirmation($reservation);

    // Vendor A received their activity
    Mail::assertQueued(VendorBookingNotificationMail::class, function (VendorBookingNotificationMail $mail) {
        return $mail->hasTo('dispatch@fastferry.com')
            && in_array('Return Speedboat Ticket', $mail->activities, true)
            && ! in_array('2-Dive Scuba Package', $mail->activities, true);
    });

    // Vendor B received their activity
    Mail::assertQueued(VendorBookingNotificationMail::class, function (VendorBookingNotificationMail $mail) {
        return $mail->hasTo('booking@coralscuba.com')
            && in_array('2-Dive Scuba Package', $mail->activities, true)
            && ! in_array('Return Speedboat Ticket', $mail->activities, true);
    });
});

test('vendor receives cancellation email when paid booking is cancelled', function () {
    Mail::fake();

    $vendor = Vendor::factory()->create([
        'operator_id' => $this->operator->id,
        'name' => 'Mount Batur Jeep',
        'reservation_email' => 'info@baturjeep.com',
    ]);

    $product = Product::factory()->create([
        'operator_id' => $this->operator->id,
        'vendor_id' => $vendor->id,
        'name' => 'Sunrise 4WD Jeep',
        'free_cancellation_hours' => 24,
    ]);

    $reservation = Reservation::factory()->create([
        'operator_id' => $this->operator->id,
        'bookable_type' => Product::class,
        'bookable_id' => $product->id,
        'status' => ReservationStatus::Confirmed,
        'requested_date' => now()->addDays(5),
    ]);

    Payment::factory()->create([
        'reservation_id' => $reservation->id,
        'status' => PaymentStatus::Paid,
        'amount' => 800000,
    ]);

    Http::fake([
        '*' => Http::response(['status' => 'SUCCESS'], 200),
    ]);

    app(GuestCancellationService::class)->cancel($reservation);

    Mail::assertQueued(VendorBookingCancelledMail::class, function (VendorBookingCancelledMail $mail) {
        return $mail->hasTo('info@baturjeep.com')
            && in_array('Sunrise 4WD Jeep', $mail->activities, true);
    });
});
