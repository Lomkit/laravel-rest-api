<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;

class BelongsToManyWithHooksResource extends BelongsToManyResource
{
    public function beforeMutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'before-mutating-belongs-to-many',
            Cache::get('before-mutating-belongs-to-many', 0) + 1,
            5
        );
    }

    public function afterMutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'after-mutating-belongs-to-many',
            Cache::get('after-mutating-belongs-to-many', 0) + 1,
            5
        );
    }

    public function beforeDestroying(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'before-destroying-belongs-to-many',
            Cache::get('before-destroying-belongs-to-many', 0) + 1,
            5
        );
    }

    public function afterDestroying(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'after-destroying-belongs-to-many',
            Cache::get('after-destroying-belongs-to-many', 0) + 1,
            5
        );
    }
}
