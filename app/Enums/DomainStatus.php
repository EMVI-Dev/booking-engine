<?php

namespace App\Enums;

enum DomainStatus: string
{
    case Pending = 'pending';
    case Verifying = 'verifying';
    case Active = 'active';
    case Failed = 'failed';
}
