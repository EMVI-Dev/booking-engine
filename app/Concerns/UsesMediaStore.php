<?php

namespace App\Concerns;

use App\Services\MediaStore;
use RuntimeException;

trait UsesMediaStore
{
    /**
     * The media store, limited to the current operator's folder when there is one.
     */
    protected function media(): MediaStore
    {
        $operator = $this->currentOperator ?? null;

        return $operator === null
            ? app(MediaStore::class)
            : app(MediaStore::class)->scopedTo($operator);
    }

    public function mediaUrl(?string $path): ?string
    {
        return $this->media()->url($path);
    }

    protected function operatorMediaDirectory(string $within = ''): string
    {
        $operator = $this->currentOperator ?? null;

        if ($operator === null) {
            throw new RuntimeException('Cannot store media without an operator.');
        }

        return $this->media()->directoryFor($operator, $within);
    }
}
