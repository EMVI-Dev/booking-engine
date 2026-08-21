<?php

namespace App\Enums;

enum WalletTransactionStatus: string
{
    case PendingEscrow = 'pending_escrow';
    case Cleared = 'cleared';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingEscrow => 'Pending Escrow',
            self::Cleared => 'Cleared',
            self::Cancelled => 'Cancelled',
        };
    }
}
