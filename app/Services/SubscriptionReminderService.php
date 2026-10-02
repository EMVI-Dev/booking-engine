<?php

namespace App\Services;

use App\Mail\SubscriptionRenewalReminderMail;
use App\Models\Operator;
use Illuminate\Support\Facades\Mail;

/**
 * Renewal reminder emails, sent by the daily scheduler or on demand from the admin desk.
 */
class SubscriptionReminderService
{
    public function __construct(private OperatorActivitySlackNotifier $slack) {}

    /**
     * Email the operator's billing inbox. Returns the address used, or null when there is none.
     *
     * @throws \Throwable when the mailer fails
     */
    public function sendRenewalReminder(Operator $operator, ?int $daysRemaining = null): ?string
    {
        $plan = $operator->plan;
        $recipient = $operator->billingRecipient();

        if (! $plan || $recipient === null) {
            return null;
        }

        $daysRemaining ??= $operator->plan_expires_at
            ? (int) now()->diffInDays($operator->plan_expires_at, false)
            : 30;

        Mail::to($recipient)->send(new SubscriptionRenewalReminderMail($operator, $plan, $daysRemaining));

        $this->slack->renewalReminderSent($operator, $daysRemaining);

        return $recipient;
    }
}
