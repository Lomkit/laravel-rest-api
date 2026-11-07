<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Database\Eloquent\Model;

trait PerformsModelOperations
{
    public function performDelete(Model $model): void
    {
        $model->delete();
    }

    public function performRestore(Model $model): void
    {
        $model->restore();
    }

    public function performForceDelete(Model $model): void
    {
        $model->forceDelete();
    }
}
