<?php

namespace App\Concerns;

use App\Models\Operator;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

trait SendsOperatorBrandedMail
{
    /**
     * From name is the operator on Agency; otherwise the platform.
     * Guest mail also replies to the operator.
     */
    protected function operatorBrandedEnvelope(?Operator $operator, string $subject, bool $replyToOperator = true): Envelope
    {
        if ($operator === null) {
            return new Envelope(subject: $subject);
        }

        $fromAddress = (string) config('mail.from.address');
        $replyTo = $replyToOperator ? $operator->outboundMailReplyToAddress() : null;

        return new Envelope(
            from: new Address($fromAddress, $operator->outboundMailFromName()),
            replyTo: $replyTo !== null ? [new Address($replyTo, $operator->name)] : [],
            subject: $subject,
        );
    }
}
