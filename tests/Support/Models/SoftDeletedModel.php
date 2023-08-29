<?php

namespace Lomkit\Rest\Tests\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;

class SoftDeletedModel extends BaseModel
{
    use SoftDeletes, HasFactory;

    protected static function newFactory()
    {
        return SoftDeletedModelFactory::new();
    }
}