<?php

namespace App\Mail;

use App\Concerns\SendsOperatorBrandedMail;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the operator a guest cancelled their own booking from the receipt page.
 */
class OperatorBookingCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SendsOperatorBrandedMail, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public float $refundAmount,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $code = $this->reservation->code ?: strtoupper(substr($this->reservation->id, -8));

        return $this->operatorBrandedEnvelope(
            $this->reservation->operator,
            "Booking Cancelled #{$code} - {$this->reservation->guest_name} ({$this->reservation->pax_count} Pax)",
            replyToOperator: false,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.operator-booking-cancelled',
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
