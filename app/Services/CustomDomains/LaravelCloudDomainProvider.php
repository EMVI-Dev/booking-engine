<?php

namespace App\Services\CustomDomains;

use App\Contracts\CustomDomainProvider;
use App\Enums\DomainStatus;
use App\Models\OperatorDomain;
use App\Services\Integrations\LaravelCloudClient;

/**
 * Laravel Cloud: each operator address is added to the production environment through the
 * Cloud API, which returns the DNS records to show and later reports hostname, origin and
 * certificate status. Each address counts toward the Cloud plan's custom domain allowance.
 *
 * @phpstan-import-type CloudDomain from LaravelCloudClient
 */
class LaravelCloudDomainProvider implements CustomDomainProvider
{
    public function __construct(
        protected LaravelCloudClient $cloud,
    ) {}

    public function name(): string
    {
        return 'laravel_cloud';
    }

    public function register(OperatorDomain $domain): void
    {
        $cloudDomain = $this->cloud->createDomain($domain->domain);

        $domain->update([
            'provider' => $this->name(),
            'provider_ref' => $cloudDomain['id'],
            'dns_records' => $this->dnsRecords($domain->domain, $cloudDomain),
            'status' => $this->statusFor($cloudDomain),
        ]);
    }

    public function check(OperatorDomain $domain): void
    {
        if (blank($domain->provider_ref)) {
            $this->register($domain);

            return;
        }

        $cloudDomain = $this->cloud->verifyDomain((string) $domain->provider_ref);
        $status = $this->statusFor($cloudDomain);
        $live = $status === DomainStatus::Active;

        $domain->update([
            'status' => $status,
            'dns_records' => $this->dnsRecords($domain->domain, $cloudDomain),
            'verified_at' => $cloudDomain['hostname_status'] === 'verified' ? ($domain->verified_at ?? now()) : null,
            'ssl_issued_at' => $live ? ($domain->ssl_issued_at ?? now()) : null,
            'last_checked_at' => now(),
        ]);
    }

    public function remove(OperatorDomain $domain): void
    {
        if (filled($domain->provider_ref)) {
            $this->cloud->deleteDomain((string) $domain->provider_ref);
        }
    }

    /**
     * Active only when Cloud has verified the name, issued the certificate and sees traffic routed.
     *
     * @param  CloudDomain  $cloudDomain
     */
    private function statusFor(array $cloudDomain): DomainStatus
    {
        $statuses = [$cloudDomain['hostname_status'], $cloudDomain['ssl_status'], $cloudDomain['origin_status']];

        return match (true) {
            in_array('failed', $statuses, true), $cloudDomain['action_required'] === 'failed' => DomainStatus::Failed,
            $statuses === ['verified', 'verified', 'verified'] => DomainStatus::Active,
            in_array('verified', $statuses, true) => DomainStatus::Verifying,
            default => DomainStatus::Pending,
        };
    }

    /**
     * The records the operator adds: the origin record (A on the apex, CNAME on a subdomain)
     * plus any certificate records Cloud asks for. The raw Cloud answer is kept for support.
     *
     * @param  CloudDomain  $cloudDomain
     * @return array{records: list<array{type: string, name: string, value: string, purpose: string}>, raw: array<string, mixed>}
     */
    private function dnsRecords(string $host, array $cloudDomain): array
    {
        $raw = $cloudDomain['dns_records'];
        $originIp = trim((string) ($raw['origin'] ?? ''));
        $originCname = rtrim(trim((string) ($raw['origin_cname'] ?? '')), '.');
        $records = [];

        if (DomainName::isApex($host) && $originIp !== '') {
            $records[] = ['type' => 'A', 'name' => '@', 'value' => $originIp, 'purpose' => 'origin'];
        } elseif ($originCname !== '') {
            $records[] = ['type' => 'CNAME', 'name' => DomainName::recordName($host), 'value' => $originCname, 'purpose' => 'origin'];
        } elseif ($originIp !== '') {
            $records[] = ['type' => 'A', 'name' => DomainName::recordName($host), 'value' => $originIp, 'purpose' => 'origin'];
        }

        foreach ((array) ($raw['ssl'] ?? []) as $sslRecord) {
            if (! is_array($sslRecord) || blank($sslRecord['name'] ?? null) || blank($sslRecord['value'] ?? null)) {
                continue;
            }

            $records[] = [
                'type' => strtoupper((string) ($sslRecord['type'] ?? 'CNAME')),
                'name' => (string) $sslRecord['name'],
                'value' => (string) $sslRecord['value'],
                'purpose' => 'ssl',
            ];
        }

        return ['records' => $records, 'raw' => $raw];
    }
}
