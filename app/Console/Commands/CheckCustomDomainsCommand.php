<?php

namespace App\Console\Commands;

use App\Services\CustomDomainService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('domains:check')]
#[Description('Re-check operator website addresses that are still waiting for DNS or their padlock.')]
class CheckCustomDomainsCommand extends Command
{
    public function handle(CustomDomainService $domains): int
    {
        $pending = $domains->pending();
        $live = 0;

        foreach ($pending as $domain) {
            if ($domains->check($domain)->isLive()) {
                $live++;
                $this->info("{$domain->domain} is live with its padlock.");
            }
        }

        $this->info("Done. Checked {$pending->count()}, {$live} now live.");

        return self::SUCCESS;
    }
}
