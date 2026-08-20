<?php

namespace App\Models;

use App\Enums\AgentUserRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AgentUser extends Pivot
{
    use HasUlids;

    protected $table = 'agent_users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'role' => AgentUserRole::class,
        ];
    }
}
