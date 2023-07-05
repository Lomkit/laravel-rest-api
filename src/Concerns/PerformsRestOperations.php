<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;

trait PerformsRestOperations
{
    public function search(SearchRequest $request) {
        $resource = static::newResource();

        $query = app()->make(QueryBuilder::class, ['resource' => $resource, 'query' => null])
            ->search($request->all());

        return $resource::newResponse()
            ->resource($resource)
            ->responsable(
                $resource->paginate($query, $request)
            );
    }

    public function mutate(MutateRequest $request) {
        $resource = static::newResource();

        DB::beginTransaction();

        $operations = app()->make(QueryBuilder::class, ['resource' => $resource, 'query' => null])
            ->tap(function ($query) use ($request) {
                self::newResource()->mutateQuery($request, $query->toBase());
            })
            ->mutate($request->all());

        DB::commit();

        return $operations;
    }


    //@TODO: stubs for responsables / rules

    //@TODO: change destroy/restore/forceDelete to allow multiple models
    public function destroy(DestroyRequest $request, $key) {
        $resource = static::newResource();

        $query = $resource->destroyQuery($request, $resource::newModel()::query());

        $model = $query->findOrFail($key);

        $this->authorizeTo('delete', $model);

        $resource->performDelete($request, $model);

        //@TODO: il faut prévoir de pouvoir load des relations ici ?
        return $resource::newResponse()
            ->resource($resource)
            ->responsable($model);
    }

    public function restore(RestoreRequest $request, $key) {
        $resource = static::newResource();

        $query = $resource->restoreQuery($request, $resource::newModel()::query());

        $model = $query->withTrashed()->findOrFail($key);

        $this->authorizeTo('restore', $model);

        $resource->performRestore($request, $model);

        return $resource::newResponse()
            ->resource($resource)
            ->responsable($model);
    }

    public function forceDelete(RestoreRequest $request, $key) {
        $resource = static::newResource();

        $query = $resource->forceDeleteQuery($request, $resource::newModel()::query());

        $model = $query->withTrashed()->findOrFail($key);

        $this->authorizeTo('forceDelete', $model);

        $resource->performForceDelete($request, $model);

        return $resource::newResponse()
            ->resource($resource)
            ->responsable($model);
    }
}