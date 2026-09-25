<?php

namespace App\Mail;

use App\Models\Operator;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperatorWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Operator $operator,
        public User $user,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name', 'TravelEngine');

        return new Envelope(
            subject: "Welcome to {$appName} - Your Tour Operator Account is Ready",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.operator-welcome',
        );
    }
}
