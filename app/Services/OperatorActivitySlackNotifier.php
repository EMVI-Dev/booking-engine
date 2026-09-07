<?php

namespace App\Services;

use App\Models\Operator;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperatorActivitySlackNotifier
{
    /**
     * Incoming webhook used for operator lifecycle alerts, or null when disabled.
     */
    public function webhookUrl(): ?string
    {
        $url = trim((string) config('services.slack.operator_webhook_url'));

        return $url !== '' ? $url : null;
    }

    public function enabled(): bool
    {
        return $this->webhookUrl() !== null;
    }

    public function operatorRegistered(Operator $operator, User $owner): void
    {
        $this->send(
            operator: $operator,
            text: "New operator registered: {$operator->name}",
            title: 'New operator registered',
            fields: [
                'Owner' => "{$owner->name} ({$owner->email})",
            ],
        );
    }

    public function planChanged(Operator $operator, string $fromPlan, string $toPlan, string $kind): void
    {
        $title = match ($kind) {
            'upgrade' => 'Operator upgraded',
            'downgrade' => 'Operator downgraded',
            'scheduled_downgrade' => 'Downgrade scheduled',
            'downgrade_cancelled' => 'Scheduled downgrade cancelled',
            'admin_assigned' => 'Admin changed operator plan',
            'complimentary' => 'Complimentary plan granted',
            'lapsed' => 'Paid plan lapsed to Starter',
            default => 'Operator plan changed',
        };

        $this->send(
            operator: $operator,
            text: "{$title}: {$operator->name} ({$fromPlan} → {$toPlan})",
            title: $title,
            fields: [
                'From' => $fromPlan,
                'To' => $toPlan,
            ],
        );
    }

    public function statusChanged(Operator $operator, string $from, string $to): void
    {
        $this->send(
            operator: $operator,
            text: "Operator status: {$operator->name} ({$from} → {$to})",
            title: 'Operator status changed',
            fields: [
                'From' => $from,
                'To' => $to,
            ],
        );
    }

    public function teammateInvited(Operator $operator, User $user, string $role): void
    {
        $this->send(
            operator: $operator,
            text: "Team member invited: {$user->email} → {$operator->name}",
            title: 'Team member invited',
            fields: [
                'Teammate' => "{$user->name} ({$user->email})",
                'Role' => $role,
            ],
        );
    }

    public function subscriptionPaid(Operator $operator, SubscriptionPayment $payment, string $fromPlan): void
    {
        $payment->loadMissing('plan');

        $title = match ($payment->type) {
            'subscription_new' => 'Subscription started',
            'subscription_renewal' => 'Subscription renewed',
            'subscription_downgrade' => 'Subscription downgraded',
            default => 'Subscription paid',
        };

        $toPlan = $payment->plan?->name ?? $operator->getPlan()->name;
        $amount = $this->formatRupiah((float) $payment->net_amount_paid);

        $this->send(
            operator: $operator,
            text: "{$title}: {$operator->name} {$amount} ({$fromPlan} → {$toPlan})",
            title: $title,
            fields: [
                'Amount' => $amount,
                'Invoice' => (string) $payment->invoice_number,
                'Type' => $this->subscriptionTypeLabel((string) $payment->type),
                'Interval' => (string) ($payment->billing_interval ?: 'monthly'),
                'Gateway' => (string) ($payment->gateway ?: '—'),
                'From' => $fromPlan,
                'To' => $toPlan,
            ],
        );
    }

    public function subscriptionPaymentFailed(Operator $operator, SubscriptionPayment $payment): void
    {
        $payment->loadMissing('plan');
        $amount = $this->formatRupiah((float) $payment->net_amount_paid);
        $planName = $payment->plan?->name ?? $operator->getPlan()->name;

        $this->send(
            operator: $operator,
            text: "Subscription payment failed: {$operator->name} {$amount} ({$planName})",
            title: 'Subscription payment failed',
            fields: [
                'Amount' => $amount,
                'Invoice' => (string) $payment->invoice_number,
                'Plan' => $planName,
                'Gateway' => (string) ($payment->gateway ?: '—'),
            ],
        );
    }

    public function subscriptionLapsed(Operator $operator, string $fromPlan, string $toPlan): void
    {
        $this->send(
            operator: $operator,
            text: "Subscription lapsed: {$operator->name} ({$fromPlan} → {$toPlan})",
            title: 'Subscription lapsed',
            fields: [
                'From' => $fromPlan,
                'To' => $toPlan,
            ],
        );
    }

    public function autoRenewChanged(Operator $operator, bool $enabled): void
    {
        $title = $enabled ? 'Auto-renew turned on' : 'Auto-renew turned off';

        $this->send(
            operator: $operator,
            text: "{$title}: {$operator->name}",
            title: $title,
            fields: [
                'Auto-renew' => $enabled ? 'On' : 'Off',
            ],
        );
    }

    public function subscriptionExtended(Operator $operator, int $days, string $expiresOn): void
    {
        $this->send(
            operator: $operator,
            text: "Subscription extended: {$operator->name} (+{$days} days)",
            title: 'Subscription extended',
            fields: [
                'Added' => $days.' days',
                'New expiry' => $expiresOn,
            ],
        );
    }

    public function renewalReminderSent(Operator $operator, int $daysUntilDue): void
    {
        $when = $daysUntilDue === 0 ? 'due today' : $daysUntilDue.' days left';

        $this->send(
            operator: $operator,
            text: "Renewal reminder sent: {$operator->name} ({$when})",
            title: 'Renewal reminder sent',
            fields: [
                'Due' => $when,
            ],
        );
    }

    protected function subscriptionTypeLabel(string $type): string
    {
        return match ($type) {
            'subscription_new' => 'New',
            'subscription_upgrade', 'upgrade' => 'Upgrade',
            'subscription_renewal' => 'Renewal',
            'subscription_downgrade' => 'Downgrade',
            default => $type !== '' ? $type : 'Payment',
        };
    }

    protected function formatRupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * @param  array<string, string>  $fields
     */
    public function send(Operator $operator, string $text, string $title, array $fields = []): void
    {
        $url = $this->webhookUrl();

        if ($url === null) {
            return;
        }

        $operator->loadMissing('plan');

        $payloadFields = [
            'Business' => $operator->name,
            'Slug' => $operator->slug,
            'Plan' => $operator->getPlan()->name,
            'Status' => $operator->status->label(),
            ...$fields,
        ];

        $blockFields = [];

        foreach ($payloadFields as $label => $value) {
            $blockFields[] = [
                'type' => 'mrkdwn',
                'text' => '*'.$label."*\n".$value,
            ];
        }

        $links = [];

        try {
            $links[] = '<'.route('admin.operators.show', $operator).'|Admin>';
        } catch (Throwable) {
            // Route may be unavailable in some console contexts.
        }

        $links[] = '<'.$operator->getStorefrontUrl().'|Storefront>';

        $payload = [
            'text' => $text,
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => $title,
                        'emoji' => true,
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => $blockFields,
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => implode('  ·  ', $links),
                        ],
                    ],
                ],
            ],
        ];

        try {
            Http::timeout(5)
                ->connectTimeout(3)
                ->retry(2, 200, fn (Throwable $exception): bool => $exception instanceof ConnectionException)
                ->post($url, $payload)
                ->throw();
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Operator activity Slack webhook failed.', [
                'operator_id' => $operator->id,
                'title' => $title,
            ]);
        }
    }
}
