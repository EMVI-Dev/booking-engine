<?php

namespace App\Services;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Operator;
use App\Models\OperatorDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

        /** @var string|null $operatorId */
        $operatorId = Cache::remember("resolved_operator_id_for_domain_{$host}", 60, function () use ($host): ?string {
            // 1. Check exact match in operator_domains (for custom domains or exact subdomains)
            // Verifying custom domains already route here at the provider, so serve them
            // while the padlock finishes; links only switch to them once Active.
            $domainRecord = OperatorDomain::query()
                ->where('domain', $host)
                ->where(fn ($query) => $query
                    ->where('status', DomainStatus::Active)
                    ->orWhere(fn ($verifying) => $verifying
                        ->where('type', DomainType::Custom)
                        ->where('status', DomainStatus::Verifying)))
                ->with('operator')
                ->first();

            if ($domainRecord && $domainRecord->operator) {
                return (string) $domainRecord->operator->id;
            }

            $subdomain = explode('.', $host)[0];
            if ($this->hostIsPlatformSubdomain($host)) {
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
        $subdomain = strtolower($operator->slug);

        $hosts = [$operator->slug];

        foreach ($this->knownPlatformSuffixes() as $suffix) {
            $hosts[] = "{$subdomain}.{$suffix}";
        }

        foreach ($operator->domains as $d) {
            $hosts[] = strtolower($d->domain);
        }

        foreach ($hosts as $h) {
            Cache::forget("resolved_operator_id_for_domain_{$h}");
        }
    }

    /**
     * Check if a host is the platform root domain.
     */
    public function isPlatformRoot(string $host): bool
    {
        $host = strtolower(trim(explode(':', $host)[0]));

        return in_array($host, $this->knownPlatformRoots(), true);
    }

    /**
     * Apex hosts that are the platform, not an operator storefront.
     *
     * @return list<string>
     */
    public function knownPlatformRoots(): array
    {
        $roots = [
            $this->getPlatformDomain(),
            'travelengine.id',
            'www.travelengine.id',
        ];

        // Local and staging hosts are never treated as the platform on production.
        if (! app()->isProduction()) {
            array_push($roots, 'localhost', '127.0.0.1', 'booking.test', 'www.booking.test', 'booking.emvi', 'www.booking.emvi', 'travelengine.emvi', 'www.travelengine.emvi');
        }

        return array_values(array_unique($roots));
    }

    /**
     * Host suffixes used for operator slugs (`{slug}.{suffix}`).
     *
     * @return list<string>
     */
    public function knownPlatformSuffixes(): array
    {
        return array_values(array_filter(
            $this->knownPlatformRoots(),
            fn (string $host): bool => ! in_array($host, ['localhost', '127.0.0.1'], true),
        ));
    }

    /**
     * The platform's own names (root, www, dev hosts) and every {slug}.platform host.
     */
    public function isPlatformHost(string $host): bool
    {
        return $this->isPlatformRoot($host) || $this->hostIsPlatformSubdomain($host);
    }

    private function hostIsPlatformSubdomain(string $host): bool
    {
        foreach ($this->knownPlatformSuffixes() as $suffix) {
            if (str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
