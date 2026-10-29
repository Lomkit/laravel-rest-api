<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;

trait PerformsModelOperations
{
    public function performDelete(RestRequest $request, Model $model): void
    {
        $model->delete();
    }

    public function performRestore(RestRequest $request, Model $model): void
    {
        $model->restore();
    }

    public function performForceDelete(RestRequest $request, Model $model): void
    {
        $model->forceDelete();
    }
}
