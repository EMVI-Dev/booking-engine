<?php

namespace App\Models;

use Database\Factories\PlatformAnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $target_plan_id
 * @property string $title
 * @property string $message
 * @property string $type
 * @property bool $is_active
 * @property bool $is_dismissible
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Plan|null $targetPlan
 */
class PlatformAnnouncement extends Model
{
    /** @use HasFactory<PlatformAnnouncementFactory> */
    use HasFactory, HasUlids;

    protected $table = 'platform_announcements';

    protected $fillable = [
        'target_plan_id',
        'title',
        'message',
        'type',
        'is_active',
        'is_dismissible',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_dismissible' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function targetPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'target_plan_id');
    }

    /**
     * Scope query to only currently active broadcasts.
     *
     * @param  Builder<PlatformAnnouncement>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $now = now();
        $query->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Scope announcements visible to a given operator.
     *
     * @param  Builder<PlatformAnnouncement>  $query
     */
    public function scopeForOperator(Builder $query, ?Operator $operator = null): void
    {
        $query->active();

        if ($operator && $operator->plan_id) {
            $query->where(function (Builder $q) use ($operator) {
                $q->whereNull('target_plan_id')->orWhere('target_plan_id', $operator->plan_id);
            });
        } else {
            $query->whereNull('target_plan_id');
        }
    }
}
