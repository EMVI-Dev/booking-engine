<?php

namespace App\Services\CustomDomains;

use App\Contracts\CustomDomainProvider;
use App\Enums\DomainStatus;
use App\Models\OperatorDomain;
use App\Services\DomainResolverService;
use RuntimeException;

/**
 * Local development and tests: nothing is registered anywhere and a check simply marks the
 * address live, so the brand settings flow can be exercised without Laravel Cloud.
 * Refuses to run in production, where addresses must be verified by Laravel Cloud.
 */
class LocalDomainProvider implements CustomDomainProvider
{
    public function __construct(
        protected DomainResolverService $resolver,
    ) {}

    public function name(): string
    {
        return 'local';
    }

    public function register(OperatorDomain $domain): void
    {
        $this->guardAgainstProduction();

        $target = $this->resolver->getPlatformDomain();

        $domain->update([
            'provider' => $this->name(),
            'provider_ref' => null,
            'dns_records' => ['records' => [[
                'type' => DomainName::isApex($domain->domain) ? 'ALIAS' : 'CNAME',
                'name' => DomainName::recordName($domain->domain),
                'value' => $target,
                'purpose' => 'origin',
            ]]],
        ]);
    }

    public function check(OperatorDomain $domain): void
    {
        $this->guardAgainstProduction();

        $domain->update([
            'status' => DomainStatus::Active,
            'verified_at' => $domain->verified_at ?? now(),
            'ssl_issued_at' => $domain->ssl_issued_at ?? now(),
            'last_checked_at' => now(),
        ]);
    }

    public function remove(OperatorDomain $domain): void
    {
        // Nothing was registered.
    }

    private function guardAgainstProduction(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The local custom domain provider cannot be used in production. Set CUSTOM_DOMAIN_PROVIDER=laravel_cloud.');
        }
    }
}
