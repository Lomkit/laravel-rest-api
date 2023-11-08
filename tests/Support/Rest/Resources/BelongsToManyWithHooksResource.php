<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;

class BelongsToManyWithHooksResource extends BelongsToManyResource
{
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'mutating-belongs-to-many',
            Cache::get('mutating-belongs-to-many', 0) + 1,
            5
        );
    }

    public function mutated(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'mutated-belongs-to-many',
            Cache::get('mutated-belongs-to-many', 0) + 1,
            5
        );
    }

    public function destroying(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'destroying-belongs-to-many',
            Cache::get('destroying-belongs-to-many', 0) + 1,
            5
        );
    }

    public function destroyed(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'destroyed-belongs-to-many',
            Cache::get('destroyed-belongs-to-many', 0) + 1,
            5
        );
    }
}
