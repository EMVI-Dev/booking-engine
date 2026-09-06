<?php

namespace App\Mail;

use App\Concerns\SendsOperatorBrandedMail;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestDepartureReminderMail extends Mailable
{
    use Queueable, SendsOperatorBrandedMail, SerializesModels;

    public function __construct(
        public Reservation $reservation
    ) {}

    public function envelope(): Envelope
    {
        $operator = $this->reservation->operator;
        $agentName = $operator?->name ?? config('app.name', 'Booking');
        $code = $this->reservation->code ?: strtoupper(substr($this->reservation->id, -8));

        return $this->operatorBrandedEnvelope(
            $operator,
            "⏰ Trip Departure Reminder #{$code} - {$agentName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.guest-departure-reminder',
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
