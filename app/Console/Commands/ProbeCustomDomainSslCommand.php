<?php

namespace App\Console\Commands;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Models\OperatorDomain;
use App\Services\DomainResolverService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('domains:probe-ssl')]
#[Description('Stamp ssl_issued_at on connected custom domains once HTTPS answers.')]
class ProbeCustomDomainSslCommand extends Command
{
    public function handle(DomainResolverService $domains): int
    {
        $pending = OperatorDomain::query()
            ->where('type', DomainType::Custom)
            ->where('status', DomainStatus::Active)
            ->whereNull('ssl_issued_at')
            ->get();

        $issued = 0;

        foreach ($pending as $domain) {
            if ($domains->markCertificateIssuedIfHttpsWorks($domain)) {
                $issued++;
                $this->info("Padlock is on for {$domain->domain}.");
            }
        }

        $this->info("Done. {$issued} padlock(s) recorded.");

        return self::SUCCESS;
    }
}
