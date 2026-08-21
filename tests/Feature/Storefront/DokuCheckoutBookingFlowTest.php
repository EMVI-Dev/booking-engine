<?php

use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Mail\GuestBookingConfirmedMail;
use App\Mail\GuestBookingCreatedMail;
use App\Mail\OperatorNewBookingNotificationMail;
use App\Models\Operator;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();

    $this->operator = Operator::factory()->create([
        'name' => 'Bali Coastal Cruises',
        'slug' => 'bali-coastal',
        'status' => OperatorStatus::Approved,
        'contact_whatsapp' => '081234567890',
    ]);

    $this->package = Package::factory()->create([
        'operator_id' => $this->operator->id,
        'title' => 'Nusa Penida Snorkel Safari',
        'price' => 500000.00,
        'status' => ListingStatus::Published,
    ]);
});

test('guest can complete full booking and test payment via internal doku sandbox', function () {
    Mail::fake();

    // 1. Submit guest booking request on booking-box
    $test = Livewire::test('storefront.booking-box', [
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
        ->assertHasNoErrors()
        ->assertRedirect();

    $reservation = Reservation::query()->where('guest_name', 'Sarah Connor')->first();
    expect($reservation)->not->toBeNull()
        ->and($reservation->pax_count)->toBe(2);

    $payment = Payment::query()->where('reservation_id', $reservation->id)->first();
    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(1050000.00) // 2 pax * 500,000 + 5% guest fee (50,000)
        ->and($payment->gateway)->toBe('doku')
        ->and($payment->status)->toBe(PaymentStatus::Pending);

    // 2. View DOKU sandbox screen
    $sandboxResponse = $this->get(route('storefront.payment.simulate', [
        'reservation' => $reservation->id,
        'payment' => $payment->id,
        'ref' => $payment->gateway_ref,
    ]));

    $sandboxResponse->assertOk()
        ->assertSee('DOKU Secure Checkout')
        ->assertSee('Sarah Connor')
        ->assertSee('1.050.000')
        ->assertSee('Simulate Successful Payment');

    // 3. Confirm Simulated Successful Payment
    $confirmResponse = $this->post(route('storefront.payment.simulate.confirm'), [
        'reservation_id' => $reservation->id,
        'status' => 'SUCCESS',
    ]);

    $confirmResponse->assertRedirect(route('storefront.reservation.receipt', $reservation->id));

    // 4. Verify payment, reservation status, operator wallet credit, and emails
    $payment->refresh();
    $reservation->refresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($reservation->status)->toBe(ReservationStatus::Confirmed);

    // Verify wallet ledger credit (100% net to operator = 1,000,000 in escrow)
    $this->operator->refresh();
    expect($this->operator->getPendingEscrowBalance())->toBe(1000000.00);

    // Verify Mailables sent to guest and operator
    Mail::assertSent(GuestBookingCreatedMail::class, function ($mail) use ($reservation) {
        return $mail->hasTo('sarah@example.com') && $mail->reservation->id === $reservation->id;
    });

    Mail::assertSent(GuestBookingConfirmedMail::class, function ($mail) use ($reservation) {
        return $mail->hasTo('sarah@example.com') && $mail->reservation->id === $reservation->id;
    });

    Mail::assertSent(OperatorNewBookingNotificationMail::class);

    // 5. Verify digital receipt & voucher view with normalized WhatsApp URL
    $receiptResponse = $this->get(route('storefront.reservation.receipt', $reservation->id));
    $receiptResponse->assertOk()
        ->assertSee('Payment Successful')
        ->assertSee('Sarah Connor')
        ->assertSee('Nusa Penida Snorkel Safari')
        ->assertSee('#'.$reservation->code)
        ->assertSee('https://wa.me/6281234567890', false);
});
