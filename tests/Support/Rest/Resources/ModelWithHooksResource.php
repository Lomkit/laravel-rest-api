<?php

namespace Lomkit\Rest\Tests\Support\Rest\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Lomkit\Rest\Concerns\Resource\DisableGates;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;
use Lomkit\Rest\Tests\Support\Rest\Actions\ModifyNumberAction;

class ModelWithHooksResource extends Resource
{
    use DisableGates;

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

    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'mutating',
            Cache::get('mutating', 0) + 1,
            5
        );
    }

    public function mutated(MutateRequest $request, array $requestBody, Model $model): void
    {
        Cache::put(
            'mutated',
            Cache::get('mutated', 0) + 1,
            5
        );
    }

    public function destroying(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'destroying',
            Cache::get('destroying', 0) + 1,
            5
        );
    }

    public function destroyed(DestroyRequest $request, Model $model): void
    {
        Cache::put(
            'destroyed',
            Cache::get('destroyed', 0) + 1,
            5
        );
    }

    public function restoring(RestoreRequest $request, Model $model): void
    {
        Cache::put(
            'restoring',
            Cache::get('restoring', 0) + 1,
            5
        );
    }

    public function restored(RestoreRequest $request, Model $model): void
    {
        Cache::put(
            'restored',
            Cache::get('restored', 0) + 1,
            5
        );
    }

    public function forceDestroying(ForceDestroyRequest $request, Model $model): void
    {
        Cache::put(
            'force-destroying',
            Cache::get('force-destroying', 0) + 1,
            5
        );
    }

    public function forceDestroyed(ForceDestroyRequest $request, Model $model): void
    {
        Cache::put(
            'force-destroyed',
            Cache::get('force-destroyed', 0) + 1,
            5
        );
    }
}
