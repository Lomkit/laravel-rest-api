<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

enum Trashed: string
{
    case With = 'with';
    case Only = 'only';

    /** @param Model|class-string<Model> $model */
    public static function supportedBy(Model|string $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }
}
