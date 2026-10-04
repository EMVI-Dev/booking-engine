<?php

namespace App\Mail;

use App\Models\Enquiry;
use App\Services\EnquiryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the operator a guest sent a question or private / group trip request.
 */
class OperatorEnquiryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Enquiry $enquiry
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $subject = $this->enquiry->isPrivateGroup()
            ? "Private trip request from {$this->enquiry->name}"
            : "New question from {$this->enquiry->name}";

        $appName = (string) config('app.name', 'TravelEngine');

        // Replying to this email reaches the guest when they left an email address.
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $appName),
            replyTo: filled($this->enquiry->email) ? [new Address((string) $this->enquiry->email, $this->enquiry->name)] : [],
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.operator-enquiry',
            with: [
                'replyUrl' => app(EnquiryService::class)->whatsAppReplyUrl($this->enquiry),
            ],
        );
    }
}
