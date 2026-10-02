<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only class that talks to Slack. Posts Block Kit payloads to an incoming webhook.
 * What to say and when is decided by the notifiers that call it.
 */
class SlackClient
{
    /**
     * Post to an incoming webhook. Retries brief network errors; never throws.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $logContext
     */
    public function post(string $webhookUrl, array $payload, array $logContext = []): bool
    {
        if (! $this->isWebhookUrl($webhookUrl)) {
            Log::warning('Slack webhook URL rejected: not a hooks.slack.com URL.', $logContext);

            return false;
        }

        try {
            Http::timeout(5)
                ->connectTimeout(3)
                ->retry(2, 200, fn (Throwable $exception): bool => $exception instanceof ConnectionException)
                ->post($webhookUrl, $payload)
                ->throw();

            return true;
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Slack webhook failed.', $logContext + ['error' => $exception->getMessage()]);

            return false;
        }
    }

    /**
     * Only Slack's own webhook host is ever called, so a bad setting cannot make the
     * platform send requests to arbitrary or internal addresses.
     */
    public function isWebhookUrl(string $url): bool
    {
        return str_starts_with($url, 'https://hooks.slack.com/');
    }
}
