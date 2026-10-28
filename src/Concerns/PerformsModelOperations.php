<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;

trait PerformsModelOperations
{
    /**
     * Build a "delete" query for the given resource.
     *
     * @param  Model  $query
     * @return void
     */
    public function performDelete(RestRequest $request, Model $model)
    {
        $model->delete();
    }

    /**
     * Build a "restore" query for the given resource.
     *
     * @param  Model  $query
     * @return void
     */
    public function performRestore(RestRequest $request, Model $model)
    {
        $model->restore();
    }

    /**
     * Build a "forceDelete" query for the given resource.
     *
     * @param  Model  $query
     * @return void
     */
    public function performForceDelete(RestRequest $request, Model $model)
    {
        $model->forceDelete();
    }
}
