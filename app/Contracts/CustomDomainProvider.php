<?php

namespace App\Contracts;

use App\Models\OperatorDomain;

/**
 * Where an operator's own website address is served and given its padlock.
 * Bound from config('domains.provider'): Laravel Cloud in production, a no-op provider locally.
 */
interface CustomDomainProvider
{
    /**
     * Short name stored on the domain row (laravel_cloud or local).
     */
    public function name(): string;

    /**
     * Register the address and store the DNS records the operator must add.
     * Throws when the provider refuses it, so nothing half-connected is kept.
     */
    public function register(OperatorDomain $domain): void;

    /**
     * Re-check DNS and the certificate, and update status, verified_at and ssl_issued_at.
     */
    public function check(OperatorDomain $domain): void;

    /**
     * Release the address at the provider. Safe to call for an address it no longer knows.
     */
    public function remove(OperatorDomain $domain): void;
}
