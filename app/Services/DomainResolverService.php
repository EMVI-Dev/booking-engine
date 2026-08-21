<?php

namespace App\Services;

use App\Enums\DomainStatus;
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

        $platformDomain = $this->getPlatformDomain();

        /** @var string|null $operatorId */
        $operatorId = Cache::remember("resolved_operator_id_for_domain_{$host}", 60, function () use ($host, $platformDomain): ?string {
            // 1. Check exact match in operator_domains (for custom domains or exact subdomains)
            $domainRecord = OperatorDomain::query()
                ->where('domain', $host)
                ->where('status', DomainStatus::Active)
                ->with('operator')
                ->first();

            if ($domainRecord && $domainRecord->operator?->isApproved()) {
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

                if ($operator && $operator->isApproved()) {
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
}
