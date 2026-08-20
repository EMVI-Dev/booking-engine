<?php

namespace App\Enums;

enum AgentUserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Reservation = 'reservation';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Reservation => 'Reservation Specialist',
            self::Finance => 'Finance',
        };
    }
}
