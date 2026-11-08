<?php

namespace Lomkit\Rest\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Events\ResourceDestroyed;
use Lomkit\Rest\Events\ResourceForceDestroyed;
use Lomkit\Rest\Events\ResourceRestored;
use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Http\BatchProgress;
use Lomkit\Rest\Http\Details\ResourceDetails;
use Lomkit\Rest\Http\MetricResponse;
use Lomkit\Rest\Http\MutateResponse;
use Lomkit\Rest\Http\OperateResponse;
use Lomkit\Rest\Http\Requests\BatchRequest;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\DetailsRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\MetricRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Response;
use Lomkit\Rest\Http\RowBatch;
use Lomkit\Rest\Search\Plan\QueryPlan;
use Lomkit\Rest\Search\Run\SearchRun;

abstract class Controller
{
    /** @var class-string<Resource> */
    public static string $resource;

    /** @return class-string<Resource> */
    public static function resourceClass(): string
    {
        if (! isset(static::$resource)) {
            throw InvalidControllerException::missingResource(static::class);
        }

        return static::$resource;
    }

    /** @return array{data: mixed} */
    public function details(DetailsRequest $request): array
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $request->context()->authorizer()->ensurePlanned(Check::onClass($resource, 'viewAny'));

        $resource->describing();

        $details = (new ResourceDetails($resource, $request->selection()))->toArray();

        $resource->described($details);

        return [
            'data' => $details,
        ];
    }

    public function search(SearchRequest $request): Response
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        return SearchRun::of($resource, $request->payload())
            ->page()
            ->response();
    }

    /** @param LengthAwarePaginator<int, Model>|Collection<int, Model> $rows */
    private function buildResponse(QueryPlan $plan, LengthAwarePaginator|Collection $rows): Response
    {
        return $plan->resource::newResponse()
            ->plan($plan)
            ->responsable($rows);
    }

    public function mutate(MutateRequest $request): MutateResponse
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        return MutateResponse::of($request, $resource);
    }

    public function operate(OperateRequest $request): OperateResponse
    {
        $request->resource($request->context()->resource(static::resourceClass()));

        return $request->payload()->action->handleRequest($request);
    }

    public function metric(MetricRequest $request): MetricResponse
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        return MetricResponse::of($request, $resource);
    }

    public function batch(BatchRequest $request): BatchProgress
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        return BatchProgress::of($request, $resource);
    }

    public function destroy(DestroyRequest $request): Response
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $models = RowBatch::authorize($resource, $request->payload()->rows(), 'delete')->write($resource->destroyingAll(...), function (Model $model) use ($request, $resource): void {
            $resource->destroying($model);

            $resource->performDelete($model);

            $request->context()->authorizer()->forget($model);

            $resource->destroyed($model);
        }, $resource->destroyedAll(...), ResourceDestroyed::class);

        return $this->buildResponse(QueryPlan::bare($resource), $models);
    }

    public function restore(RestoreRequest $request): Response
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $models = RowBatch::authorize($resource, $request->payload()->rows(), 'restore')->write($resource->restoringAll(...), function (Model $model) use ($request, $resource): void {
            $resource->restoring($model);

            $resource->performRestore($model);

            $request->context()->authorizer()->forget($model);

            $resource->restored($model);
        }, $resource->restoredAll(...), ResourceRestored::class);

        return $this->buildResponse(QueryPlan::bare($resource), $models);
    }

    public function forceDestroy(ForceDestroyRequest $request): Response
    {
        $request->resource($resource = $request->context()->resource(static::resourceClass()));

        $models = RowBatch::authorize($resource, $request->payload()->rows(), 'forceDelete')->write($resource->forceDestroyingAll(...), function (Model $model) use ($request, $resource): void {
            $resource->forceDestroying($model);

            $resource->performForceDelete($model);

            $request->context()->authorizer()->forget($model);

            $resource->forceDestroyed($model);
        }, $resource->forceDestroyedAll(...), ResourceForceDestroyed::class);

        return $this->buildResponse(QueryPlan::bare($resource), $models);
    }
}
