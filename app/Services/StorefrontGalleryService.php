<?php

namespace App\Services;

use App\Models\Operator;
use App\Models\OperatorGalleryPhoto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The storefront photo gallery: the only place gallery photos are added, captioned,
 * reordered or removed. Photos go through MediaStore (resized, WebP, R2) inside the
 * operator's own folder, and the count is capped by the plan (gallery_photo_limit).
 */
class StorefrontGalleryService
{
    public const MAX_WIDTH = 1600;

    public const CAPTION_MAX = 150;

    public function __construct(
        private MediaStore $media,
    ) {}

    public function limitFor(Operator $operator): int
    {
        return max(0, (int) $operator->getPlan()->gallery_photo_limit);
    }

    public function remainingFor(Operator $operator): int
    {
        return max(0, $this->limitFor($operator) - $operator->galleryPhotos()->count());
    }

    /**
     * Photos shown on the storefront. After a downgrade, photos beyond the new limit are
     * kept (nothing is deleted) but not shown until the operator upgrades or removes some.
     *
     * @return Collection<int, OperatorGalleryPhoto>
     */
    public function visiblePhotos(Operator $operator): Collection
    {
        $limit = $this->limitFor($operator);

        if ($limit === 0) {
            return new Collection;
        }

        return $operator->galleryPhotos()->limit($limit)->get();
    }

    /**
     * @throws ValidationException when the plan's gallery is full
     */
    public function add(Operator $operator, UploadedFile $file, ?string $caption = null): OperatorGalleryPhoto
    {
        return DB::transaction(function () use ($operator, $file, $caption): OperatorGalleryPhoto {
            // Serialise uploads per operator so two at once cannot pass the limit together.
            Operator::query()->whereKey($operator->id)->lockForUpdate()->first(['id']);

            if ($this->remainingFor($operator) <= 0) {
                throw ValidationException::withMessages([
                    'gallery' => $this->limitFor($operator) === 0
                        ? __('Your plan does not include a gallery.')
                        : __('Your gallery is full (:limit photos on your plan). Remove a photo or upgrade to add more.', ['limit' => $this->limitFor($operator)]),
                ]);
            }

            $store = $this->media->scopedTo($operator);
            $path = $store->storeUpload($file, $store->directoryFor($operator, 'gallery'), self::MAX_WIDTH);

            return $operator->galleryPhotos()->create([
                'path' => $path,
                'caption' => $this->cleanCaption($caption),
                'sort_order' => ((int) $operator->galleryPhotos()->max('sort_order')) + 1,
            ]);
        });
    }

    public function updateCaption(Operator $operator, string $photoId, ?string $caption): void
    {
        $this->photoOf($operator, $photoId)->update(['caption' => $this->cleanCaption($caption)]);
    }

    /**
     * Save a new order. Ids that are not this operator's photos are ignored.
     *
     * @param  list<string>  $orderedIds
     */
    public function reorder(Operator $operator, array $orderedIds): void
    {
        $owned = $operator->galleryPhotos()->pluck('id')->all();
        $position = 0;

        DB::transaction(function () use ($operator, $orderedIds, $owned, &$position): void {
            foreach ($orderedIds as $id) {
                if (in_array($id, $owned, true)) {
                    OperatorGalleryPhoto::query()->whereKey($id)->where('operator_id', $operator->id)->update(['sort_order' => ++$position]);
                }
            }
        });
    }

    public function remove(Operator $operator, string $photoId): void
    {
        $photo = $this->photoOf($operator, $photoId);
        $path = $photo->path;
        $photo->delete();

        $this->media->scopedTo($operator)->delete($path);
    }

    public function url(OperatorGalleryPhoto $photo): ?string
    {
        return $this->media->url($photo->path);
    }

    private function photoOf(Operator $operator, string $photoId): OperatorGalleryPhoto
    {
        /** @var OperatorGalleryPhoto $photo */
        $photo = $operator->galleryPhotos()->whereKey($photoId)->firstOrFail();

        return $photo;
    }

    private function cleanCaption(?string $caption): ?string
    {
        $caption = trim((string) $caption);

        return $caption === '' ? null : mb_substr($caption, 0, self::CAPTION_MAX);
    }
}
