<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\DetailsRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Response;
use Lomkit\Rest\Query\ScoutBuilder;

trait PerformsRestOperations
{
    /** @return array{data: mixed} */
    public function details(DetailsRequest $request): array
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $this->beforeDetails($request);

        $resource->authorizeTo('viewAny', $resource::modelClass());

        return [
            'data' => $resource->jsonSerialize(),
        ];
    }

    public function search(SearchRequest $request): Response
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $this->beforeSearch($request);

        $builder = $request->context()->isTextSearch() ? ScoutBuilder::class : QueryBuilder::class;

        $query = app()->make($builder, ['resource' => $resource, 'query' => null])
            ->search($request->input('search', []));

        $this->afterSearch($request);

        return $this->buildResponse($resource, $resource->paginate($query, $request));
    }

    private function buildResponse(Resource $resource, mixed $responsable): Response
    {
        return $resource::newResponse()
            ->resource($resource)
            ->responsable($responsable);
    }

    /** @return array<string, mixed> */
    public function mutate(MutateRequest $request): array
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $this->beforeMutate($request);

        $operations = DB::transaction(function () use ($request, $resource) {
            $queryBuilder = app()->make(EloquentQueryBuilder::class, ['resource' => $resource, 'query' => null]);
            $resource->mutateQuery($request, $queryBuilder->toBase());

            return $queryBuilder->mutate($request->all());
        });

        $this->afterMutate($request);

        return $operations;
    }

    public function operate(OperateRequest $request): IlluminateResponse
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $this->beforeOperate($request);

        $actionInstance = $resource->action($request, $request->actionKey());

        $modelsImpacted = $actionInstance->handleRequest($request);

        $this->afterOperate($request);

        return response([
            'data' => [
                'impacted' => $modelsImpacted,
            ],
        ]);
    }

    public function destroy(DestroyRequest $request): Response
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $this->beforeDestroy($request);

        // The perimeter runs in a subquery so that an "or" inside it cannot swallow the
        // id allow-list appended afterwards, which binds tighter than it does
        $query = $resource::newModel()::query()
            ->where(function ($query) use ($request, $resource) {
                $resource->destroyQuery($request, $query);
            });

        $models = $query
            ->whereIn($resource::newModel()->getKeyName(), $request->input('resources'))
            ->get();

        foreach ($models as $model) {
            $resource->authorizeTo('delete', $model);
        }

        foreach ($models as $model) {
            $resource->destroying($request, $model);

            $resource->performDelete($request, $model);

            $request->context()->authorizer()->forget($model);

            $resource->destroyed($request, $model);
        }

        $this->afterDestroy($request);

        return $resource::newResponse()
            ->resource($resource)
            ->responsable($models);
    }

    public function restore(RestoreRequest $request): Response
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $this->beforeRestore($request);

        // The perimeter runs in a subquery so that an "or" inside it cannot swallow the
        // id allow-list appended afterwards, which binds tighter than it does
        $query = $resource::newModel()::query()
            ->where(function ($query) use ($request, $resource) {
                $resource->restoreQuery($request, $query);
            });

        $models = $query
            ->withTrashed()
            ->whereIn($resource::newModel()->getKeyName(), $request->input('resources'))
            ->get();

        foreach ($models as $model) {
            $resource->authorizeTo('restore', $model);
        }

        foreach ($models as $model) {
            $resource->restoring($request, $model);

            $resource->performRestore($request, $model);

            $request->context()->authorizer()->forget($model);

            $resource->restored($request, $model);
        }

        $this->afterRestore($request);

        return $resource::newResponse()
            ->resource($resource)
            ->responsable($models);
    }

    public function forceDestroy(ForceDestroyRequest $request): Response
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $this->beforeForceDestroy($request);

        // The perimeter runs in a subquery so that an "or" inside it cannot swallow the
        // id allow-list appended afterwards, which binds tighter than it does
        $query = $resource::newModel()::query()
            ->where(function ($query) use ($request, $resource) {
                $resource->forceDestroyQuery($request, $query);
            });

        $models = $query
            ->withTrashed()
            ->whereIn($resource::newModel()->getKeyName(), $request->input('resources'))
            ->get();

        foreach ($models as $model) {
            $resource->authorizeTo('forceDelete', $model);
        }

        foreach ($models as $model) {
            $resource->forceDestroying($request, $model);

            $resource->performForceDelete($request, $model);

            $request->context()->authorizer()->forget($model);

            $resource->forceDestroyed($request, $model);
        }

        $this->afterForceDestroy($request);

        return $resource::newResponse()
            ->resource($resource)
            ->responsable($models);
    }
}
