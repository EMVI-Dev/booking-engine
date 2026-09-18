<?php

namespace App\Mail;

use App\Concerns\SendsOperatorBrandedMail;
use App\Models\Reservation;
use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendorBookingCancelledMail extends Mailable
{
    use Queueable, SendsOperatorBrandedMail, SerializesModels;

    /**
     * @param  array<int, string>  $activities
     */
    public function __construct(
        public Reservation $reservation,
        public Vendor $vendor,
        public array $activities = []
    ) {}

    public function envelope(): Envelope
    {
        $operator = $this->reservation->operator;
        $operatorName = $operator?->name ?? config('app.name', 'Booking');
        $operatorEmail = $operator?->email ?: (string) config('mail.from.address');
        $code = $this->reservation->code ?: strtoupper(substr($this->reservation->id, -8));

        return new Envelope(
            from: new Address($operatorEmail, $operatorName),
            replyTo: $operatorEmail ? [new Address($operatorEmail, $operatorName)] : [],
            subject: "Booking Cancelled - {$operatorName} #{$code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vendor-booking-cancelled',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
