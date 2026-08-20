<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use Database\Factories\AgentDomainFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $agent_id
 * @property string $domain
 * @property DomainType $type
 * @property bool $is_primary
 * @property DomainStatus $status
 * @property Carbon|null $verified_at
 * @property Carbon|null $ssl_issued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent|null $agent
 */
class AgentDomain extends Model
{
    /** @use HasFactory<AgentDomainFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'agent_id',
        'domain',
        'type',
        'is_primary',
        'status',
        'verified_at',
        'ssl_issued_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DomainType::class,
            'status' => DomainStatus::class,
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
            'ssl_issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function isActive(): bool
    {
        return $this->status === DomainStatus::Active;
    }

    public function isSubdomain(): bool
    {
        return $this->type === DomainType::Subdomain;
    }
}
