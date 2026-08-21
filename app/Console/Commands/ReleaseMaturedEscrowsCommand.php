<?php

namespace App\Console\Commands;

use App\Services\WalletService;
use Illuminate\Console\Command;

class ReleaseMaturedEscrowsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:release-escrows';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release matured wallet escrow funds whose departure date has passed into available balances';

    /**
     * Execute the console command.
     */
    public function handle(WalletService $walletService): int
    {
        $this->info('Releasing matured escrow funds...');

        $count = $walletService->releaseMaturedEscrows();

        $this->info("Successfully released {$count} escrow transaction(s) to cleared available balance.");

        return self::SUCCESS;
    }
}
