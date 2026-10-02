<?php

namespace App\Services;

use App\Contracts\CustomDomainProvider;
use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Services\CustomDomains\DomainName;
use App\Services\Integrations\LaravelCloudException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * An operator's own website address (Agency plan): connect, check, disconnect.
 * Where the address is served is the bound CustomDomainProvider (Laravel Cloud, or the local no-op provider).
 */
class CustomDomainService
{
    public function __construct(
        protected CustomDomainProvider $provider,
        protected DomainResolverService $resolver,
    ) {}

    public function currentFor(Operator $operator): ?OperatorDomain
    {
        return $operator->domains()->where('type', DomainType::Custom)->first();
    }

    /**
     * Connect (or change) the operator's own address and return it with the DNS records to add.
     *
     * @throws ValidationException under the custom_domain key
     */
    public function connect(Operator $operator, string $input): OperatorDomain
    {
        if (! $operator->hasFeature('custom_domain')) {
            throw $this->invalid(__('Your own website address is included on the Agency plan.'));
        }

        $host = DomainName::normalize($input);

        if ($host === null) {
            throw $this->invalid(__('Type a website address like tours.yourname.com.'));
        }

        if ($this->resolver->isPlatformHost($host)) {
            throw $this->invalid(__('Use your own domain here. Your TravelEngine address already works.'));
        }

        if (OperatorDomain::query()->where('domain', $host)->where('operator_id', '!=', $operator->id)->exists()) {
            throw $this->invalid(__('Another business is already using that address.'));
        }

        $current = $this->currentFor($operator);

        if ($current?->domain === $host && $current->provider === $this->provider->name()) {
            return $current;
        }

        if ($current !== null) {
            $this->release($current);
        }

        $domain = $operator->domains()->create([
            'domain' => $host,
            'type' => DomainType::Custom,
            'is_primary' => false,
            'status' => DomainStatus::Pending,
            'provider' => $this->provider->name(),
        ]);

        try {
            $this->provider->register($domain);
        } catch (LaravelCloudException $e) {
            $domain->delete();
            report($e);

            throw $this->invalid($e->isValidationError()
                ? __('That address could not be added. Check the spelling, or contact us if it is already connected somewhere else.')
                : __('We could not add that address right now. Please try again in a few minutes.'));
        } catch (RuntimeException $e) {
            $domain->delete();
            report($e);

            throw $this->invalid(__('We could not add that address right now. Please try again in a few minutes.'));
        }

        $this->resolver->clearOperatorDomainCache($operator);

        return $domain->refresh();
    }

    /**
     * Remove the operator's own address here and at the provider.
     */
    public function disconnect(Operator $operator): void
    {
        $current = $this->currentFor($operator);

        if ($current === null) {
            return;
        }

        $this->release($current);
        $this->resolver->clearOperatorDomainCache($operator);
    }

    /**
     * Re-check DNS and the padlock now. Returns the refreshed row; never throws for provider outages.
     */
    public function check(OperatorDomain $domain): OperatorDomain
    {
        $wasLive = $domain->isLive();

        try {
            $this->provider->check($domain);
        } catch (Throwable $e) {
            report($e);
            $domain->forceFill(['last_checked_at' => now()])->save();
        }

        $domain->refresh();

        if ($wasLive !== $domain->isLive() && $domain->operator !== null) {
            $this->resolver->clearOperatorDomainCache($domain->operator);
        }

        return $domain;
    }

    /**
     * Custom addresses still waiting for DNS or a padlock, on plans that include them.
     *
     * @return Collection<int, OperatorDomain>
     */
    public function pending(): Collection
    {
        return OperatorDomain::query()
            ->where('type', DomainType::Custom)
            ->where(fn ($query) => $query
                ->whereIn('status', [DomainStatus::Pending, DomainStatus::Verifying])
                ->orWhere(fn ($active) => $active->where('status', DomainStatus::Active)->whereNull('ssl_issued_at')))
            ->with('operator.plan')
            ->get()
            ->filter(fn (OperatorDomain $domain): bool => (bool) $domain->operator?->hasFeature('custom_domain'))
            ->values();
    }

    /**
     * Free the address at the provider first, then forget it. A provider outage keeps the row,
     * so the address is never orphaned at the provider (it still counts toward the allowance).
     */
    private function release(OperatorDomain $domain): void
    {
        try {
            $this->provider->remove($domain);
        } catch (Throwable $e) {
            report($e);

            throw $this->invalid(__('We could not remove :domain right now. Please try again in a few minutes.', ['domain' => $domain->domain]));
        }

        $domain->delete();
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['custom_domain' => $message]);
    }
}
