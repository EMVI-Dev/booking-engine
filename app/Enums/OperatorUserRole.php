<?php

namespace App\Enums;

enum OperatorUserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Reservation = 'reservation';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Manager',
            self::Reservation => 'Bookings',
            self::Finance => 'Money',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full access, including who is on the team.',
            self::Admin => 'Run the business day to day. Cannot remove the owner.',
            self::Reservation => 'Bookings, calendar, and guest list only.',
            self::Finance => 'Wallet, payouts, and the plan bill only.',
        };
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Owner, self::Admin => [
                'manageTeam',
                'manageBilling',
                'manageWallet',
                'manageCatalog',
                'manageReservations',
                'manageSettings',
            ],
            self::Reservation => [
                'manageReservations',
            ],
            self::Finance => [
                'manageWallet',
                'manageBilling',
            ],
        };
    }

    public function allows(string $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }

    public function canInvite(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }
}
