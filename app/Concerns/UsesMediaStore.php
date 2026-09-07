<?php

namespace App\Concerns;

use App\Services\MediaStore;
use RuntimeException;

trait UsesMediaStore
{
    protected function media(): MediaStore
    {
        return app(MediaStore::class);
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
