<?php

namespace App\Mail;

use App\Models\Operator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperatorAccountSuspendedInactivityMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Operator $operator,
        public int $inactiveDays,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name', 'TravelEngine');

        return new Envelope(
            subject: "Important: {$this->operator->name} Account Suspended due to Inactivity on {$appName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.operator-account-suspended-inactivity',
        );
    }
}
