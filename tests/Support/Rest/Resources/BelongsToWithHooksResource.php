<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;

class BelongsToWithHooksResource extends BelongsToResource
{
    public function beforeMutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'before-mutating-belongs-to',
            Cache::get('before-mutating-belongs-to', 0) + 1,
            5
        );
    }

    public function afterMutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'after-mutating-belongs-to',
            Cache::get('after-mutating-belongs-to', 0) + 1,
            5
        );
    }

    public function beforeDestroying(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'before-destroying-belongs-to',
            Cache::get('before-destroying-belongs-to', 0) + 1,
            5
        );
    }

    public function afterDestroying(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'after-destroying-belongs-to',
            Cache::get('after-destroying-belongs-to', 0) + 1,
            5
        );
    }
}
