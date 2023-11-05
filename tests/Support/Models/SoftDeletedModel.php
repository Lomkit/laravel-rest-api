<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;

class SoftDeletedModel extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected static function newFactory()
    {
        return SoftDeletedModelFactory::new();
    }
}
