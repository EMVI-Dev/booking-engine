<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * The only class that talks to the Laravel Cloud API. Today: custom domains on the
 * production environment (create, check, verify, delete).
 *
 * @see https://cloud.laravel.com/docs/api/introduction
 *
 * @phpstan-type CloudDomain array{
 *     id: string,
 *     name: string,
 *     stage: ?string,
 *     hostname_status: string,
 *     ssl_status: string,
 *     origin_status: string,
 *     action_required: ?string,
 *     dns_records: array<string, mixed>
 * }
 */
class LaravelCloudClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.laravel_cloud.token')) && filled(config('services.laravel_cloud.environment'));
    }

    /**
     * Applications and their environments the token can see (to find LARAVEL_CLOUD_ENVIRONMENT_ID).
     *
     * @return list<array{application: string, environment_id: string, environment: string, vanity_domain: ?string, status: ?string}>
     */
    public function environments(): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('applications', ['include' => 'environments']), requireEnvironment: false);

        if (! $response->successful()) {
            throw $this->failure($response, 'list applications');
        }

        $applications = [];
        foreach ((array) $response->json('data') as $application) {
            if (is_array($application) && isset($application['id'])) {
                $applications[(string) $application['id']] = (string) ($application['attributes']['name'] ?? $application['id']);
            }
        }

        $environments = [];
        foreach ((array) $response->json('included') as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'environments' || ! isset($item['id'])) {
                continue;
            }

            $applicationId = (string) ($item['relationships']['application']['data']['id'] ?? '');
            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];

            $environments[] = [
                'application' => $applications[$applicationId] ?? '-',
                'environment_id' => (string) $item['id'],
                'environment' => (string) ($attributes['name'] ?? ''),
                'vanity_domain' => isset($attributes['vanity_domain']) ? (string) $attributes['vanity_domain'] : null,
                'status' => isset($attributes['status']) ? (string) $attributes['status'] : null,
            ];
        }

        return $environments;
    }

    /**
     * Add a custom domain to the environment. Real-time verification: the operator points
     * the domain at Cloud and Cloud issues the certificate, no ownership TXT step.
     *
     * @return CloudDomain
     */
    public function createDomain(string $name): array
    {
        $environment = (string) config('services.laravel_cloud.environment');

        $response = $this->send(fn (PendingRequest $http) => $http->post("environments/{$environment}/domains", [
            'name' => $name,
            'allow_downtime' => true,
            'verification_method' => 'real_time',
            'cloudflare_strategy' => 'none',
        ]));

        return $this->domainFrom($response, "add {$name}");
    }

    /**
     * Current state of a domain. With $verify, Cloud re-checks DNS first.
     *
     * @return CloudDomain
     */
    public function getDomain(string $domainId, bool $verify = false): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get(
            'domains/'.rawurlencode($domainId),
            $verify ? ['verify' => 'true'] : [],
        ));

        return $this->domainFrom($response, "check {$domainId}");
    }

    /**
     * Ask Cloud to re-check the DNS records now.
     *
     * @return CloudDomain
     */
    public function verifyDomain(string $domainId): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post('domains/'.rawurlencode($domainId).'/verify'));

        return $this->domainFrom($response, "verify {$domainId}");
    }

    /**
     * Remove a domain. A domain that is already gone counts as removed.
     */
    public function deleteDomain(string $domainId): void
    {
        $response = $this->send(fn (PendingRequest $http) => $http->delete('domains/'.rawurlencode($domainId)));

        if ($response->successful() || $response->status() === 404) {
            return;
        }

        throw $this->failure($response, "remove {$domainId}");
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call, bool $requireEnvironment = true): Response
    {
        if (blank(config('services.laravel_cloud.token'))) {
            throw new RuntimeException('LARAVEL_CLOUD_API_TOKEN is not configured.');
        }

        if ($requireEnvironment && blank(config('services.laravel_cloud.environment'))) {
            throw new RuntimeException('LARAVEL_CLOUD_ENVIRONMENT_ID is not configured. Run php artisan cloud:environments to find it.');
        }

        try {
            return $call(
                Http::baseUrl(rtrim((string) config('services.laravel_cloud.base_url', 'https://cloud.laravel.com/api'), '/'))
                    ->withToken((string) config('services.laravel_cloud.token'))
                    ->accept('application/vnd.api+json')
                    ->asJson()
                    ->timeout(15)
                    ->connectTimeout(5),
            );
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Laravel Cloud could not be reached.', previous: $e);
        }
    }

    /**
     * @return CloudDomain
     */
    private function domainFrom(Response $response, string $action): array
    {
        if (! $response->successful()) {
            throw $this->failure($response, $action);
        }

        $data = $response->json('data');
        $attributes = is_array($data) ? ($data['attributes'] ?? []) : [];

        if (! is_array($data) || ! isset($data['id']) || ! is_array($attributes)) {
            throw new RuntimeException("Laravel Cloud returned an unexpected response when trying to {$action}.");
        }

        return [
            'id' => (string) $data['id'],
            'name' => (string) ($attributes['name'] ?? ''),
            'stage' => isset($attributes['stage']) ? (string) $attributes['stage'] : null,
            'hostname_status' => (string) ($attributes['hostname_status'] ?? 'pending'),
            'ssl_status' => (string) ($attributes['ssl_status'] ?? 'pending'),
            'origin_status' => (string) ($attributes['origin_status'] ?? 'pending'),
            'action_required' => isset($attributes['action_required']) ? (string) $attributes['action_required'] : null,
            'dns_records' => is_array($attributes['dns_records'] ?? null) ? $attributes['dns_records'] : [],
        ];
    }

    private function failure(Response $response, string $action): RuntimeException
    {
        $message = $response->json('message');
        $errors = $response->json('errors');
        $detail = is_array($errors) ? collect($errors)->flatten()->implode(' ') : '';

        return new LaravelCloudException(
            trim("Laravel Cloud could not {$action}: ".(is_string($message) ? $message : 'HTTP '.$response->status()).' '.$detail),
            $response->status(),
        );
    }
}
