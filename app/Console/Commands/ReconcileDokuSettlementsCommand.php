<?php

namespace App\Console\Commands;

use App\Services\PaymentMatchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('platform:match-payments')]
#[Description('Find guest payments that were received but never added to an operator wallet.')]
class ReconcileDokuSettlementsCommand extends Command
{
    public function handle(PaymentMatchService $paymentMatchService): int
    {
        $exceptions = $paymentMatchService->unmatchedPaidCharges();

        $this->info('Checked paid guest charges. Unmatched: '.count($exceptions));

        return self::SUCCESS;
    }
}
