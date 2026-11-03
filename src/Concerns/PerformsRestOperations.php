<?php

namespace Lomkit\Rest\Concerns;

use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Contracts\EloquentQueryBuilder;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\DetailsRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Response;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Query\EngineSearch;
use Lomkit\Rest\Query\QueryBuilderFactory;
use Lomkit\Rest\Search\Plan\SearchPlanner;

trait PerformsRestOperations
{
    /** @return array{data: mixed} */
    public function details(DetailsRequest $request): array
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $request->context()->authorizer()->ensurePlanned(Check::onClass($resource, 'viewAny'));

        $this->beforeDetails($request);

        return [
            'data' => $resource->jsonSerialize(),
        ];
    }

    public function search(SearchRequest $request): Response
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $this->beforeSearch($request);

        $text = $request->context()->text($resource);

        if ($text->usesEngine()) {
            $page = (new EngineSearch($resource, $text, $request->payload()))->paginate($request);

            $this->afterSearch($request);

            return $this->buildResponse($resource, $page);
        }

        $plan = SearchPlanner::paged($resource, $request->payload(), $text);
        $query = QueryBuilderFactory::make($resource)->search($plan);

        $this->afterSearch($request);

        return $this->buildResponse($resource, $text->run(fn () => $resource->paginate($query, $plan->limit->perPage(), $plan->limit->page())));
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

            return $queryBuilder->mutate(['mutate' => array_map(fn (MutationNode $node): array => $node->toArray(), $request->payload())]);
        });

        $this->afterMutate($request);

        return $operations;
    }

    public function operate(OperateRequest $request): IlluminateResponse
    {
        $request->resource($request->context()->resource(static::resourceClass()));

        $this->beforeOperate($request);

        $modelsImpacted = $request->payload()->action->handleRequest($request);

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

        $models = $request->payload()
            ->constrain($request->keysQuery($resource))
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

        $models = $request->payload()
            ->constrain($request->keysQuery($resource))
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

        $models = $request->payload()
            ->constrain($request->keysQuery($resource))
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
