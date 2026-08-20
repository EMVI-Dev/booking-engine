<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PackageProduct extends Pivot
{
    use HasUlids;

    protected $table = 'package_products';

    public $incrementing = false;

    protected $keyType = 'string';
}
