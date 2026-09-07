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
            self::Owner => 'Every part of the desk, including who is on the team.',
            self::Admin => 'Same desk as the owner, except they cannot remove the owner.',
            self::Reservation => 'Day-to-day trips: bookings, calendar, and the guest list.',
            self::Finance => 'Money only: wallet, payouts, and the plan bill.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Owner => 'fa-crown',
            self::Admin => 'fa-user-tie',
            self::Reservation => 'fa-calendar-check',
            self::Finance => 'fa-wallet',
        };
    }

    public function inviteCaveat(): ?string
    {
        return match ($this) {
            self::Admin => 'Cannot remove the owner.',
            default => null,
        };
    }

    /**
     * Desk areas this role can and cannot use.
     *
     * @return list<array{key: string, label: string, allowed: bool}>
     */
    public function deskScope(): array
    {
        return [
            [
                'key' => 'reservations',
                'label' => 'Bookings, calendar & guests',
                'allowed' => $this->allows('manageReservations'),
            ],
            [
                'key' => 'catalog',
                'label' => 'Activities, packages & coupons',
                'allowed' => $this->allows('manageCatalog'),
            ],
            [
                'key' => 'storefront',
                'label' => 'Brand & storefront',
                'allowed' => $this->allows('manageSettings'),
            ],
            [
                'key' => 'wallet',
                'label' => 'Wallet & payouts',
                'allowed' => $this->allows('manageWallet'),
            ],
            [
                'key' => 'billing',
                'label' => 'Plan & payout bank',
                'allowed' => $this->allows('manageBilling'),
            ],
            [
                'key' => 'team',
                'label' => 'Team invites',
                'allowed' => $this->allows('manageTeam'),
            ],
        ];
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
