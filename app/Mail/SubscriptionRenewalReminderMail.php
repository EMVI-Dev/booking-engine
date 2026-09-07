<?php

namespace App\Mail;

use App\Models\Operator;
use App\Models\Plan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Operator $operator,
        public Plan $plan,
        public int $daysRemaining
    ) {}

    public function envelope(): Envelope
    {
        $planName = $this->plan->name;

        if ($this->daysRemaining <= 0) {
            $subject = "Important: Your {$planName} Subscription has Expired";
        } elseif ($this->daysRemaining === 1) {
            $subject = "Reminder: Your {$planName} Subscription Expires Tomorrow";
        } else {
            $subject = "Reminder: Your {$planName} Subscription Expires in {$this->daysRemaining} Days";
        }

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-renewal-reminder',
        );
    }
}
