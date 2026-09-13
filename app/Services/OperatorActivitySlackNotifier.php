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
                'Owner' => $owner->name,
                'Email' => $owner->email,
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
                'Teammate' => $user->name,
                'Email' => $user->email,
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
        $gateway = trim((string) ($payment->gateway ?: ''));

        $fields = [
            'Amount' => $amount,
            'Invoice' => (string) $payment->invoice_number,
            'Type' => $this->subscriptionTypeLabel((string) $payment->type),
            'From' => $fromPlan,
            'To' => $toPlan,
        ];

        if ($gateway !== '') {
            $fields['Gateway'] = $gateway;
        }

        $this->send(
            operator: $operator,
            text: "{$title}: {$operator->name} {$amount} ({$fromPlan} → {$toPlan})",
            title: $title,
            fields: $fields,
        );
    }

    public function subscriptionPaymentFailed(Operator $operator, SubscriptionPayment $payment): void
    {
        $payment->loadMissing('plan');
        $amount = $this->formatRupiah((float) $payment->net_amount_paid);
        $planName = $payment->plan?->name ?? $operator->getPlan()->name;
        $gateway = trim((string) ($payment->gateway ?: ''));

        $fields = [
            'Amount' => $amount,
            'Invoice' => (string) $payment->invoice_number,
            'Plan' => $planName,
        ];

        if ($gateway !== '') {
            $fields['Gateway'] = $gateway;
        }

        $this->send(
            operator: $operator,
            text: "Subscription payment failed: {$operator->name} {$amount} ({$planName})",
            title: 'Subscription payment failed',
            fields: $fields,
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

    protected function iconUrl(): ?string
    {
        $url = rtrim((string) config('app.url'), '/').'/favicon.png';
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        // Slack fetches the icon from the public internet; local Herd hosts 404 as a broken image.
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.test') || str_ends_with($host, '.emvi')) {
            return null;
        }

        return $url;
    }

    /**
     * @param  array<string, string>  $fields
     * @return list<array{type: string, text: array{type: string, text: string, emoji: bool}, style?: string, url: string, action_id: string}>
     */
    protected function actionButtons(Operator $operator, ?string $adminUrl, string $storefrontUrl): array
    {
        $actions = [];

        if ($adminUrl !== null) {
            $actions[] = [
                'type' => 'button',
                'text' => [
                    'type' => 'plain_text',
                    'text' => 'Open Admin',
                    'emoji' => false,
                ],
                'style' => 'primary',
                'url' => $adminUrl,
                'action_id' => 'open_admin_'.$operator->id,
            ];
        }

        $actions[] = [
            'type' => 'button',
            'text' => [
                'type' => 'plain_text',
                'text' => 'Open Storefront',
                'emoji' => false,
            ],
            'url' => $storefrontUrl,
            'action_id' => 'open_storefront_'.$operator->id,
        ];

        return $actions;
    }

    /**
     * Build card body: operator name, then a monospace box with padded label/value rows.
     *
     * @param  array<string, string>  $fields
     */
    protected function formatCardBody(string $operatorName, array $fields): string
    {
        $lines = ['*'.$operatorName.'*'];
        $rows = [];

        foreach ($fields as $label => $value) {
            $display = trim((string) $value);
            if ($display === '') {
                continue;
            }

            $rows[(string) $label] = $display;
        }

        if ($rows === []) {
            return $lines[0];
        }

        $labelWidth = max(array_map(strlen(...), array_keys($rows)));
        $padded = [];

        foreach ($rows as $label => $display) {
            $padded[] = str_pad($label, $labelWidth).' : '.$display;
        }

        $lines[] = '';
        $lines[] = '```';
        $lines[] = implode("\n", $padded);
        $lines[] = '```';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, string>  $fields
     * @return array{text: string, blocks: list<array<string, mixed>>}
     */
    public function buildPayload(Operator $operator, string $text, string $title, array $fields = []): array
    {
        $operator->loadMissing('plan');

        $adminUrl = null;

        try {
            $adminUrl = route('admin.operators.show', $operator);
        } catch (Throwable) {
            // Route may be unavailable in some console contexts.
        }

        $storefrontUrl = $operator->getStorefrontUrl();

        $card = [
            'type' => 'card',
            'title' => [
                'type' => 'mrkdwn',
                'text' => $title,
                'verbatim' => false,
            ],
            'subtitle' => [
                'type' => 'mrkdwn',
                'text' => 'Slug · '.$operator->slug,
                'verbatim' => false,
            ],
            'body' => [
                'type' => 'mrkdwn',
                'text' => $this->formatCardBody($operator->name, $fields),
                'verbatim' => false,
            ],
            'subtext' => [
                'type' => 'mrkdwn',
                'text' => now()->timezone((string) config('app.timezone'))->format('M j, Y · H:i'),
                'verbatim' => false,
            ],
            'actions' => $this->actionButtons($operator, $adminUrl, $storefrontUrl),
        ];

        $iconUrl = $this->iconUrl();

        if ($iconUrl !== null) {
            $card = [
                'icon' => [
                    'type' => 'image',
                    'image_url' => $iconUrl,
                    'alt_text' => (string) config('app.name', 'TravelEngine'),
                ],
                ...$card,
            ];
        }

        return [
            'text' => $text,
            'blocks' => [$card],
        ];
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

        $payload = $this->buildPayload($operator, $text, $title, $fields);

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
