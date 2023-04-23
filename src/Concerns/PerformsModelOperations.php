<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;

trait PerformsModelOperations
{

    /**
     * Build a query for the given resource.
     *
     * @param  \Lomkit\Rest\Http\Requests\RestRequest  $request
     * @param  \Illuminate\Database\Eloquent\Model  $query
     * @return void
     */
    public function performDelete(RestRequest $request, Model $model) {
        $model->delete();
    }

    public function performRestore(RestRequest $request, Model $model) {
        $model->restore();
    }

    public function performForceDelete(RestRequest $request, Model $model) {
        $model->forceDelete();
    }
}