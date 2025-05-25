<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

    protected function buildGatesForModel(Model $model, Resource $resource, array $gates)
    {
        return array_merge(
            in_array('view', $gates) ? [config('rest.gates.names.authorized_to_view')         => $resource->authorizedTo('view', $model)] : [],
            in_array('update', $gates) ? [config('rest.gates.names.authorized_to_update')         => $resource->authorizedTo('update', $model)] : [],
            in_array('delete', $gates) ? [config('rest.gates.names.authorized_to_delete')         => $resource->authorizedTo('delete', $model)] : [],
            in_array('restore', $gates) ? [config('rest.gates.names.authorized_to_restore')         => $resource->authorizedTo('restore', $model)] : [],
            in_array('forceDelete', $gates) ? [config('rest.gates.names.authorized_to_force_delete')         => $resource->authorizedTo('forceDelete', $model)] : [],
        );
    }

    /**
     * Convert an Eloquent model into an array representation for the HTTP response.
     *
     * This method transforms the given model by selecting only the specified attributes and aggregates as defined in the request parameters or resource. If authorization gating is enabled and gate parameters are provided, it appends the corresponding authorization data. Additionally, it recursively processes any loaded relations—returning pivot data when applicable and mapping related models (or collections of models) using the resource’s configuration.
     *
     * @param Model         $model        The Eloquent model instance to be converted.
     * @param resource      $resource     The resource defining the fields and structure of the response.
     * @param array         $requestArray Request parameters that control field selection, aggregates, and authorization gates.
     * @param Relation|null $relation     Optional relation context for processing nested relationships.
     *
     * @return array The structured array representation of the model, including attributes and recursively processed relations.
     */
    public function modelToResponse(Model $model, Resource $resource, array $requestArray, ?Relation $relation = null)
    {
        $currentRequestArray = $relation === null ? $requestArray : collect($requestArray['includes'] ?? [])
            ->first(function ($include) use ($relation) {
                return preg_match('/(?:\.\b)?'.$relation->relation.'\b/', $include['relation']);
            });

        return array_merge(
            // toArray to take advantage of Laravel's logic
            collect($model->attributesToArray())
                ->only(
                    array_merge(
                        isset($currentRequestArray['selects']) ?
                                collect($currentRequestArray['selects'])->pluck('field')->toArray() :
                                $resource->getFields(app()->make(RestRequest::class)),
                        // Here we add the aggregates
                        collect($currentRequestArray['aggregates'] ?? [])
                            ->map(function ($aggregate) {
                                return $aggregate['alias'] ?? Str::snake($aggregate['relation']).'_'.$aggregate['type'].(isset($aggregate['field']) ? '_'.$aggregate['field'] : '');
                            })
                            ->toArray()
                    )
                )
                ->when($resource->isGatingEnabled() && isset($currentRequestArray['gates']), function ($attributes) use ($currentRequestArray, $resource, $model) {
                    return $attributes->put(
                        config('rest.gates.key'),
                        $this->buildGatesForModel($model, $resource, $currentRequestArray['gates'])
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

                    if ($modelRelation instanceof Model) {
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
                $this->resource->isGatingEnabled() && in_array('create', $request->input('search.gates', [])) ? [
                    config('rest.gates.key') => [
                        config('rest.gates.names.authorized_to_create') => $this->resource->authorizedTo('create', $this->resource::newModel()::class),
                    ],
                ] : []
            );

            $restLengthAwarePaginator->through(function ($model) use ($request) {
                return $this->map($model, $this->modelToResponse($model, $this->resource, $request->input('search', [])));
            });

            return $restLengthAwarePaginator;
        } elseif ($this->responsable instanceof Collection) {
            $data = $this->responsable->map(function ($model) use ($request) {
                return $this->map($model, $this->modelToResponse($model, $this->resource, $request->input('search', [])));
            });
        }

        return [
            'data' => $data ?? $this->map($this->responsable, $this->modelToResponse($this->responsable, $this->resource, $request->input('search', []))),
            'meta' => array_merge(
                $this->resource->isGatingEnabled() && in_array('create', $request->input('search.gates', [])) ? [
                    config('rest.gates.key') => [
                        config('rest.gates.names.authorized_to_create') => $this->resource->authorizedTo('create', $this->resource::newModel()::class),
                    ],
                ] : []
            ),
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
