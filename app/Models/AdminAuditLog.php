<?php

namespace App\Models;

use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One platform-admin action. Rows are written once and never edited or deleted.
 *
 * @property string $id
 * @property string|null $user_id
 * @property string|null $actor_email
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $operator_id
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Operator|null $operator
 */
class AdminAuditLog extends Model
{
    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'actor_email',
        'action',
        'subject_type',
        'subject_id',
        'operator_id',
        'context',
        'ip_address',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Admin audit log entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Admin audit log entries are immutable.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Operator, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
