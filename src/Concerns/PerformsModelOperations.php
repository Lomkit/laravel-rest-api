<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidResourceException;

trait PerformsModelOperations
{
    public function performDelete(Model $model): void
    {
        $model->delete();
    }

    public function performRestore(Model $model): void
    {
        if (! method_exists($model, 'restore')) {
            throw InvalidResourceException::unrestorableModel(static::class, $model::class);
        }

        $model->restore();
    }

    public function performForceDelete(Model $model): void
    {
        $model->forceDelete();
    }
}
