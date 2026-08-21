<?php

namespace App\Models;

use App\Enums\OperatorUserRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class OperatorUser extends Pivot
{
    use HasUlids;

    protected $table = 'operator_users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'role' => OperatorUserRole::class,
        ];
    }
}
