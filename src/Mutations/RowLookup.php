<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;

final readonly class RowLookup
{
    public function find(Resource $resource, int|string|null $key): Model
    {
        $model = $resource::newModel();

        return $model::query()->findOrFail($key);
    }
}
