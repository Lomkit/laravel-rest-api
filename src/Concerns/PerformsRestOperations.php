<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\DetailRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;

trait PerformsRestOperations
{
    /**
     * Retrieve details of a resource.
     *
     * @param DetailRequest $request
     *
     * @return array
     */
    public function detail(DetailRequest $request)
    {
        $request->resource($resource = static::newResource());

        $resource->authorizeTo('viewAny', $resource::$model);

        return [
            'data' => $resource->jsonSerialize(),
        ];
    }

    /**
     * Search for resources based on the given criteria.
     *
     * @param SearchRequest $request
     *
     * @return mixed
     */
    public function search(SearchRequest $request)
    {
        $request->resource($resource = static::newResource());

        $query = app()->make(QueryBuilder::class, ['resource' => $resource, 'query' => null])
            ->search($request->all());

        return $resource::newResponse()
            ->resource($resource)
            ->responsable(
                $resource->paginate($query, $request)
            );
    }

    /**
     * Mutate resources based on the given request data.
     *
     * @param MutateRequest $request
     *
     * @return mixed
     */
    public function mutate(MutateRequest $request)
    {
        $request->resource($resource = static::newResource());

        DB::beginTransaction();

        $operations = app()->make(QueryBuilder::class, ['resource' => $resource, 'query' => null])
            ->tap(function ($query) use ($request) {
                self::newResource()->mutateQuery($request, $query->toBase());
            })
            ->mutate($request->all());

        DB::commit();

        return $operations;
    }

    /**
     * Perform a specific action on the resource.
     *
     * @param OperateRequest $request
     * @param string         $action
     *
     * @return mixed
     */
    public function operate(OperateRequest $request, $action)
    {
        $request->resource($resource = static::newResource());

        $actionInstance = $resource->action($request, $action);

        $modelsImpacted = $actionInstance->handleRequest($request);

        return response([
            'data' => [
                'impacted' => $modelsImpacted,
            ],
        ]);
    }

    /**
     * Delete resources based on the given request.
     *
     * @param DestroyRequest $request
     *
     * @return mixed
     */
    public function destroy(DestroyRequest $request)
    {
        $request->resource($resource = static::newResource());

        $query = $resource->destroyQuery($request, $resource::newModel()::query());

        $models = $query
            ->whereIn($resource::newModel()->getKeyName(), $request->input('resources'))
            ->get();

        foreach ($models as $model) {
            self::newResource()->authorizeTo('delete', $model);

            $resource->performDelete($request, $model);
        }

        return $resource::newResponse()
            ->resource($resource)
            ->responsable($models);
    }

    /**
     * Restore resources based on the given request.
     *
     * @param RestoreRequest $request
     *
     * @return mixed
     */
    public function restore(RestoreRequest $request)
    {
        $request->resource($resource = static::newResource());

        $query = $resource->restoreQuery($request, $resource::newModel()::query());

        $models = $query
            ->withTrashed()
            ->whereIn($resource::newModel()->getKeyName(), $request->input('resources'))
            ->get();

        foreach ($models as $model) {
            self::newResource()->authorizeTo('restore', $model);

            $resource->performRestore($request, $model);
        }

        return $resource::newResponse()
            ->resource($resource)
            ->responsable($models);
    }

    /**
     * Force delete resources based on the given request.
     *
     * @param ForceDestroyRequest $request
     *
     * @return mixed
     */
    public function forceDelete(ForceDestroyRequest $request)
    {
        $request->resource($resource = static::newResource());

        $query = $resource->forceDeleteQuery($request, $resource::newModel()::query());

        $models = $query
            ->withTrashed()
            ->whereIn($resource::newModel()->getKeyName(), $request->input('resources'))
            ->get();

        foreach ($models as $model) {
            self::newResource()->authorizeTo('forceDelete', $model);

            $resource->performForceDelete($request, $model);
        }

        return $resource::newResponse()
            ->resource($resource)
            ->responsable($models);
    }
}
