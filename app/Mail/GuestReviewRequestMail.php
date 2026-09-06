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

class GuestReviewRequestMail extends Mailable
{
    use Queueable, SendsOperatorBrandedMail, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public string $reviewUrl
    ) {}

    public function envelope(): Envelope
    {
        $operator = $this->reservation->operator;
        $agentName = $operator?->name ?? config('app.name', 'Booking');

        return $this->operatorBrandedEnvelope(
            $operator,
            "⭐ How was your experience with {$agentName}?",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.guest-review-request',
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
