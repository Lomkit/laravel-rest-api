<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Lomkit\Rest\Concerns\Resource\DisableAutomaticGates;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;
use Lomkit\Rest\Tests\Support\Rest\Actions\ModifyNumberAction;

class ModelWithHooksResource extends Resource
{
    use DisableAutomaticGates;

    public static $model = SoftDeletedModel::class;

    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('belongsToRelation', BelongsToWithHooksResource::class),
            BelongsToMany::make('belongsToManyRelation', BelongsToManyWithHooksResource::class)
                ->withPivotRules([
                    'number' => 'numeric',
                ])
                ->withPivotFields(['created_at', 'number']),
        ];
    }

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'name',
            'number',
            'string',
        ];
    }

    public function defaultOrderBy(RestRequest $request): array
    {
        return [
            'id' => 'asc',
        ];
    }

    public function actions(RestRequest $request): array
    {
        return [
            ModifyNumberAction::make(),
        ];
    }

    public function beforeMutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'before-mutating',
            Cache::get('before-mutating', 0) + 1,
            5
        );
    }

    public function afterMutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'after-mutating',
            Cache::get('after-mutating', 0) + 1,
            5
        );
    }

    public function beforeDestroying(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'before-destroying',
            Cache::get('before-destroying', 0) + 1,
            5
        );
    }

    public function afterDestroying(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'after-destroying',
            Cache::get('after-destroying', 0) + 1,
            5
        );
    }
}
