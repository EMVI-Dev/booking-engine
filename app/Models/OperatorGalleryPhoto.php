<?php

namespace App\Models;

use Database\Factories\OperatorGalleryPhotoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One photo in an operator's storefront gallery. Written only by StorefrontGalleryService.
 *
 * @property string $id
 * @property string $operator_id
 * @property string $path
 * @property string|null $caption
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Operator $operator
 */
class OperatorGalleryPhoto extends Model
{
    /** @use HasFactory<OperatorGalleryPhotoFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'operator_id',
        'path',
        'caption',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Operator, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
