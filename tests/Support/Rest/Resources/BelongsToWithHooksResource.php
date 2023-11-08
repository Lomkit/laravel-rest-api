<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;

class BelongsToWithHooksResource extends BelongsToResource
{
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'mutating-belongs-to',
            Cache::get('mutating-belongs-to', 0) + 1,
            5
        );
    }

    public function mutated(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'mutated-belongs-to',
            Cache::get('mutated-belongs-to', 0) + 1,
            5
        );
    }

    public function destroying(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'destroying-belongs-to',
            Cache::get('destroying-belongs-to', 0) + 1,
            5
        );
    }

    public function destroyed(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'destroyed-belongs-to',
            Cache::get('destroyed-belongs-to', 0) + 1,
            5
        );
    }
}
