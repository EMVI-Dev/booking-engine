<?php

namespace App\Models;

use Database\Factories\EnquiryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A guest question or private / group trip request sent from the storefront contact form.
 * Written only by EnquiryService.
 *
 * @property string $id
 * @property string $operator_id
 * @property string $type
 * @property string $name
 * @property string $whatsapp
 * @property string|null $email
 * @property Carbon|null $preferred_date
 * @property int|null $group_size
 * @property string $message
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Operator $operator
 */
class Enquiry extends Model
{
    /** @use HasFactory<EnquiryFactory> */
    use HasFactory, HasUlids;

    public const TYPE_GENERAL = 'general';

    public const TYPE_PRIVATE_GROUP = 'private_group';

    public const TYPES = [self::TYPE_GENERAL, self::TYPE_PRIVATE_GROUP];

    protected $fillable = [
        'operator_id',
        'type',
        'name',
        'whatsapp',
        'email',
        'preferred_date',
        'group_size',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'group_size' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Operator, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * @param  Builder<Enquiry>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function isPrivateGroup(): bool
    {
        return $this->type === self::TYPE_PRIVATE_GROUP;
    }

    public function typeLabel(): string
    {
        return $this->isPrivateGroup() ? __('Private / group trip') : __('General question');
    }
}
