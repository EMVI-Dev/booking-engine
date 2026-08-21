<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperatorNewBookingNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation
    ) {}

    public function envelope(): Envelope
    {
        $code = $this->reservation->code ?: strtoupper(substr($this->reservation->id, -8));
        $guestName = $this->reservation->guest_name;

        return new Envelope(
            subject: "🎉 New Booking #{$code} - {$guestName} ({$this->reservation->pax_count} Pax)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.operator-new-booking-notification',
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
