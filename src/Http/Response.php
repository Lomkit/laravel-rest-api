<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\Relation;

class Response implements Responsable
{
    protected $responsable;
    protected Resource $resource;

    public function responsable($responsable)
    {
        return tap($this, function () use ($responsable) {
            $this->responsable = $responsable;
        });
    }

    public function resource(Resource $resource)
    {
        return tap($this, function () use ($resource) {
            $this->resource = $resource;
        });
    }

    protected function buildGatesForModel(Model $model)
    {
        return [
            config('rest.automatic_gates.names.authorized_to_view')         => Gate::allows('view', $model),
            config('rest.automatic_gates.names.authorized_to_update')       => Gate::allows('update', $model),
            config('rest.automatic_gates.names.authorized_to_delete')       => Gate::allows('delete', $model),
            config('rest.automatic_gates.names.authorized_to_restore')      => Gate::allows('restore', $model),
            config('rest.automatic_gates.names.authorized_to_force_delete') => Gate::allows('forceDelete', $model),
        ];
    }

    public function modelToResponse(Model $model, Resource $resource, array $requestArray, Relation $relation = null)
    {
        $currentRequestArray = $relation === null ? $requestArray : collect($requestArray['includes'])
            ->first(function ($include) use ($relation) {
                return preg_match('/(?:\.\b)?'.$relation->relation.'\b/', $include['relation']);
            });

        return array_merge(
            // toArray to take advantage of Laravel's logic
            collect($model->attributesToArray())
                ->only(
                    array_merge(
                        isset($requestArray['selects']) ?
                                collect($requestArray['selects'])->pluck('field')->toArray() :
                                $resource->getFields(app()->make(RestRequest::class)),
                        // Here we add the aggregates
                        collect($requestArray['aggregates'] ?? [])
                            ->map(function ($aggregate) {
                                return Str::snake($aggregate['relation']).'_'.$aggregate['type'].(isset($aggregate['field']) ? '_'.$aggregate['field'] : '');
                            })
                            ->toArray()
                    )
                )
                ->when($resource->isAutomaticGatingEnabled(), function ($attributes) use ($model) {
                    return $attributes->put(
                        config('rest.automatic_gates.key'),
                        $this->buildGatesForModel($model)
                    );
                })
                ->toArray(),
            collect($model->getRelations())
                ->mapWithKeys(function ($modelRelation, $relationName) use ($requestArray, $relation, $resource) {
                    $key = Str::snake($relationName);

                    if (is_null($modelRelation)) {
                        return [
                            $key => null,
                        ];
                    } elseif ($modelRelation instanceof Pivot) {
                        return [
                            $key => collect($modelRelation->toArray())
                                ->only($relation->getPivotFields())
                                ->toArray(),
                        ];
                    }

                    $relationConcrete = $resource->relation($relationName);
                    $relationResource = $relationConcrete->resource();
                    $requestArrayRelation = collect($requestArray['includes'])
                        ->first(function ($include) use ($relationName) {
                            return preg_match('/(?:\.\b)?'.$relationName.'\b/', $include['relation']);
                        });

                    // We reapply the limits in case of BelongsToManyRelation where we can't apply limits easily
                    if ($modelRelation instanceof Collection) {
                        $modelRelation = $modelRelation->take($requestArrayRelation['limit'] ?? 50);
                    } elseif ($modelRelation instanceof Model) {
                        return [
                            $key => $this->modelToResponse(
                                $modelRelation,
                                $relationResource,
                                $requestArray,
                                $relationConcrete
                            ),
                        ];
                    }

                    return [
                        $key => $modelRelation
                            ->map(fn ($collectionRelation) => $this->modelToResponse($collectionRelation, $relationResource, $requestArray, $relationConcrete))
                            ->toArray(),
                    ];
                })
                ->toArray()
        );
    }

    public function toResponse($request)
    {
        if ($this->responsable instanceof LengthAwarePaginator) {
            $restLengthAwarePaginator = new \Lomkit\Rest\Pagination\LengthAwarePaginator(
                $this->responsable->items(),
                $this->responsable->total(),
                $this->responsable->perPage(),
                $this->responsable->currentPage(),
                $this->responsable->getOptions(),
                $this->resource->isAutomaticGatingEnabled() ? [
                    config('rest.automatic_gates.key') => [
                        config('rest.automatic_gates.names.authorized_to_create') => Gate::allows('create', $this->resource::newModel()::class),
                    ],
                ] : []
            );

            $restLengthAwarePaginator->through(function ($model) use ($request) {
                return $this->map($model, $this->modelToResponse($model, $this->resource, $request->input()));
            });

            return $restLengthAwarePaginator;
        } elseif ($this->responsable instanceof Collection) {
            $data = $this->responsable->map(function ($model) use ($request) {
                return $this->map($model, $this->modelToResponse($model, $this->resource, $request->input()));
            });
        }

        return [
            'data' => $data ?? $this->map($this->responsable, $this->modelToResponse($this->responsable, $this->resource, $request->input())),
            'meta' => [
                config('rest.automatic_gates.key') => [
                    config('rest.automatic_gates.names.authorized_to_create') => Gate::allows('create', $this->resource::newModel()::class),
                ],
            ],
        ];
    }

    /**
     * This map on each model returned by the API, use it at your ease.
     *
     * @var \Illuminate\Database\Eloquent\Model
     * @var array
     *
     * @return array
     */
    protected function map(\Illuminate\Database\Eloquent\Model $model, array $responseModel): array
    {
        return $responseModel;
    }

    // @TODO: this class needs a refactor because it has grown a lot since the beginning also with the "lengthAwarePaginator"
    // @TODO: recursive response for those gates ?
}
