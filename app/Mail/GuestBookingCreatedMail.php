<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestBookingCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation
    ) {}

    public function envelope(): Envelope
    {
        $agentName = $this->reservation->agent->name ?? config('app.name', 'Booking');
        $code = $this->reservation->code ?: strtoupper(substr($this->reservation->id, -8));

        return new Envelope(
            subject: "⏳ Action Required: Complete Payment for Booking #{$code} - {$agentName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.guest-booking-created',
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
