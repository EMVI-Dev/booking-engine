<?php

namespace App\Services;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Operator;
use App\Models\OperatorDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class DomainResolverService
{
    /**
     * Get the configured root platform domain.
     */
    public function getPlatformDomain(): string
    {
        $appUrlHost = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST);

        return strtolower(is_string($appUrlHost) && $appUrlHost !== 'localhost' ? $appUrlHost : 'booking.test');
    }

    /**
     * Resolve the active Operator from an incoming HTTP request or hostname string.
     */
    public function resolveOperator(Request|string $requestOrHost): ?Operator
    {
        $rawHost = $requestOrHost instanceof Request
            ? ((string) ($requestOrHost->header('Host') ?: $requestOrHost->getHost()))
            : $requestOrHost;

        $host = strtolower(trim(explode(':', $rawHost)[0]));

        // If it is exactly the platform root domain or local host, return null (central platform mode)
        if ($this->isPlatformRoot($host)) {
            return null;
        }

        $platformDomain = $this->getPlatformDomain();

        /** @var string|null $operatorId */
        $operatorId = Cache::remember("resolved_operator_id_for_domain_{$host}", 60, function () use ($host, $platformDomain): ?string {
            // 1. Check exact match in operator_domains (for custom domains or exact subdomains)
            $domainRecord = OperatorDomain::query()
                ->where('domain', $host)
                ->where('status', DomainStatus::Active)
                ->with('operator')
                ->first();

            if ($domainRecord && $domainRecord->operator) {
                return (string) $domainRecord->operator->id;
            }

            // 2. Check if it's a subdomain on the platform (e.g. {slug}.platform.com, {slug}.booking.test, {slug}.booking.emvi)
            $subdomain = explode('.', $host)[0];
            if (
                str_ends_with($host, '.'.$platformDomain) ||
                str_ends_with($host, '.booking.test') ||
                str_ends_with($host, '.booking.emvi') ||
                str_ends_with($host, '.platform.com')
            ) {
                $operator = Operator::query()
                    ->where('slug', $subdomain)
                    ->first();

                if ($operator) {
                    return (string) $operator->id;
                }
            }

            return null;
        });

        if (! $operatorId) {
            return null;
        }

        return Operator::find($operatorId);
    }

    /**
     * Clear cached domain resolutions for the given operator.
     */
    public function clearOperatorDomainCache(Operator $operator): void
    {
        $platformDomain = $this->getPlatformDomain();
        $subdomain = strtolower($operator->slug);

        $hosts = [
            "{$subdomain}.{$platformDomain}",
            "{$subdomain}.booking.test",
            "{$subdomain}.booking.emvi",
            "{$subdomain}.platform.com",
            $operator->slug,
        ];

        foreach ($operator->domains as $d) {
            $hosts[] = strtolower($d->domain);
        }

        foreach ($hosts as $h) {
            Cache::forget("resolved_operator_id_for_domain_{$h}");
        }
    }

    /**
     * @deprecated Use resolveOperator() instead.
     */
    public function resolveAgent(Request|string $requestOrHost): ?Operator
    {
        return $this->resolveOperator($requestOrHost);
    }

    /**
     * Check if a host is the platform root domain.
     */
    public function isPlatformRoot(string $host): bool
    {
        $host = strtolower(trim(explode(':', $host)[0]));
        $platformDomain = $this->getPlatformDomain();

        return in_array($host, [$platformDomain, 'localhost', '127.0.0.1', 'booking.test', 'booking.emvi'], true);
    }

    /**
     * Public IPv4 addresses guests should A-record an apex name to.
     *
     * @return list<string>
     */
    public function expectedPlatformIpv4(): array
    {
        return $this->uniqueIps([
            ...$this->configuredIps((string) config('domains.public_ipv4'), FILTER_FLAG_IPV4),
            ...array_values(array_filter(
                $this->addressRecords($this->getPlatformDomain(), DNS_A),
                fn (string $ip): bool => $this->isRoutablePublicIp($ip, FILTER_FLAG_IPV4),
            )),
        ]);
    }

    /**
     * Public IPv6 addresses guests should AAAA-record an apex name to.
     *
     * @return list<string>
     */
    public function expectedPlatformIpv6(): array
    {
        return $this->uniqueIps([
            ...$this->configuredIps((string) config('domains.public_ipv6'), FILTER_FLAG_IPV6),
            ...array_values(array_filter(
                $this->addressRecords($this->getPlatformDomain(), DNS_AAAA),
                fn (string $ip): bool => $this->isRoutablePublicIp($ip, FILTER_FLAG_IPV6),
            )),
        ]);
    }

    /**
     * The single IPv4 to print on the connect-your-name card.
     *
     * Live DNS of the platform host is ignored here so operators are not
     * shown a pile of A records. Loopback is also hidden.
     *
     * @return list<string>
     */
    public function instructionIpv4(): array
    {
        return $this->instructionIps(
            $this->configuredIps((string) config('domains.public_ipv4'), FILTER_FLAG_IPV4),
            FILTER_FLAG_IPV4,
        );
    }

    /**
     * The single IPv6 to print on the connect-your-name card.
     *
     * @return list<string>
     */
    public function instructionIpv6(): array
    {
        return $this->instructionIps(
            $this->configuredIps((string) config('domains.public_ipv6'), FILTER_FLAG_IPV6),
            FILTER_FLAG_IPV6,
        );
    }

    /**
     * Whether Caddy may ask Let's Encrypt for a padlock on this host.
     */
    public function customDomainMayReceiveCertificate(string $host): bool
    {
        $host = $this->normalizeCustomHost($host);

        if ($host === null) {
            return false;
        }

        $record = OperatorDomain::query()
            ->where('domain', $host)
            ->where('type', DomainType::Custom)
            ->where('status', DomainStatus::Active)
            ->with('operator')
            ->first();

        if (! $record?->operator) {
            return false;
        }

        return $record->operator->isApproved()
            && $record->operator->hasFeature('custom_domain');
    }

    /**
     * Stamp ssl_issued_at after HTTPS answers with a trusted certificate.
     */
    public function markCertificateIssuedIfHttpsWorks(OperatorDomain $domain): bool
    {
        if ($domain->ssl_issued_at !== null) {
            return true;
        }

        if (! $this->customDomainMayReceiveCertificate($domain->domain)) {
            return false;
        }

        try {
            Http::timeout(8)
                ->connectTimeout(3)
                ->withOptions(['verify' => true])
                ->get('https://'.$domain->domain.'/');
        } catch (Throwable) {
            return false;
        }

        $domain->update(['ssl_issued_at' => now()]);

        return true;
    }

    /**
     * @return non-empty-string|null
     */
    private function normalizeCustomHost(string $host): ?string
    {
        $host = strtolower(trim(explode(':', $host)[0]));
        $host = rtrim($host, '.');

        if ($host === '' || str_contains($host, '/') || str_contains($host, '..')) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return null;
        }

        return $host;
    }

    /**
     * Whether a custom website address already points at this platform.
     *
     * Apex names (yourname.com) usually cannot CNAME. An A / AAAA to this
     * server, or a flattened ALIAS that resolves to the same address, counts.
     */
    public function customDomainPointsHere(string $domain, string $targetHost): bool
    {
        $domain = strtolower(trim(explode(':', $domain)[0]));
        $domain = rtrim($domain, '.');

        return $this->recordsPointHere(
            $this->cnameTargets($domain),
            $this->addressRecords($domain, DNS_A),
            $this->addressRecords($domain, DNS_AAAA),
            $targetHost,
            $this->expectedPlatformIpv4(),
            $this->expectedPlatformIpv6(),
        );
    }

    /**
     * @param  list<string>  $cnameTargets
     * @param  list<string>  $ipv4s
     * @param  list<string>  $ipv6s
     * @param  list<string>  $platformIpv4
     * @param  list<string>  $platformIpv6
     */
    public function recordsPointHere(
        array $cnameTargets,
        array $ipv4s,
        array $ipv6s,
        string $targetHost,
        array $platformIpv4 = [],
        array $platformIpv6 = [],
    ): bool {
        $expectedHost = strtolower(rtrim($targetHost, '.'));

        foreach ($cnameTargets as $target) {
            if (strtolower(rtrim((string) $target, '.')) === $expectedHost) {
                return true;
            }
        }

        if (array_intersect($this->uniqueIps($ipv4s), $this->uniqueIps($platformIpv4)) !== []) {
            return true;
        }

        return array_intersect($this->uniqueIps($ipv6s), $this->uniqueIps($platformIpv6)) !== [];
    }

    /**
     * @return list<string>
     */
    private function cnameTargets(string $domain): array
    {
        $targets = [];

        foreach ($this->lookupDns($domain, DNS_CNAME) as $record) {
            if (isset($record['target']) && is_string($record['target']) && $record['target'] !== '') {
                $targets[] = strtolower(rtrim($record['target'], '.'));
            }
        }

        return $this->uniqueStrings($targets);
    }

    /**
     * @return list<string>
     */
    private function addressRecords(string $domain, int $type): array
    {
        $key = $type === DNS_AAAA ? 'ipv6' : 'ip';
        $ips = [];

        foreach ($this->lookupDns($domain, $type) as $record) {
            if (isset($record[$key]) && is_string($record[$key]) && $record[$key] !== '') {
                $ips[] = strtolower($record[$key]);
            }
        }

        return $this->uniqueIps($ips);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lookupDns(string $domain, int $type): array
    {
        $records = @dns_get_record($domain, $type);

        return is_array($records) ? $records : [];
    }

    /**
     * @return list<string>
     */
    private function configuredIps(?string $value, int $familyFlag): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $ips = [];

        foreach (explode(',', $value) as $candidate) {
            $ip = strtolower(trim($candidate));

            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, $familyFlag) !== false) {
                $ips[] = $ip;
            }
        }

        return $this->uniqueIps($ips);
    }

    /**
     * @param  list<string>  $ips
     * @return list<string>
     */
    private function instructionIps(array $ips, int $familyFlag): array
    {
        $printable = array_values(array_filter(
            $ips,
            fn (string $ip): bool => $this->isDisplayableInstructionIp($ip, $familyFlag),
        ));

        return array_slice($printable, 0, 1);
    }

    private function isDisplayableInstructionIp(string $ip, int $familyFlag): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, $familyFlag | FILTER_FLAG_NO_PRIV_RANGE) === false) {
            return false;
        }

        return ! str_starts_with($ip, '127.') && $ip !== '::1';
    }

    private function isRoutablePublicIp(string $ip, int $familyFlag): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, $familyFlag | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @param  list<string>  $ips
     * @return list<string>
     */
    private function uniqueIps(array $ips): array
    {
        $normalized = [];

        foreach ($ips as $ip) {
            $ip = strtolower(trim((string) $ip));

            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                $normalized[] = $ip;
            }
        }

        return $this->uniqueStrings($normalized);
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        return array_values(array_unique($values));
    }
}
