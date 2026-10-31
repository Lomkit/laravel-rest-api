<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\FieldSet;
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

    protected function buildGatesForModel(Model|string $model, Resource $resource, array $gates)
    {
        $nameMap = [
            'create' => config('rest.gates.names.authorized_to_create'),
            'view' => config('rest.gates.names.authorized_to_view'),
            'update' => config('rest.gates.names.authorized_to_update'),
            'delete' => config('rest.gates.names.authorized_to_delete'),
            'restore' => config('rest.gates.names.authorized_to_restore'),
            'forceDelete' => config('rest.gates.names.authorized_to_force_delete'),
        ];

        $result = [];

        if (config('rest.gates.message.enabled', false)) {
            foreach ($gates as $gate) {
                if (isset($nameMap[$gate])) {
                    $authorizedTo = $resource->authorizedTo($gate, $model);
                    $result[$nameMap[$gate]]['allowed'] = $authorizedTo->allowed();
                    $result[$nameMap[$gate]]['message'] = $authorizedTo->message();
                }
            }
        } else {
            trigger_deprecation('lomkit/laravel-rest-api', '2.17.0', 'In Laravel Rest Api 3 it won\'t be possible to use the old gate schema, please upgrade as quickly as possible. See: https://laravel-rest-api.lomkit.com/digging-deeper/gates#policy-message-in-gates');
            foreach ($gates as $gate) {
                if (isset($nameMap[$gate])) {
                    $authorizedTo = $resource->authorizedTo($gate, $model);
                    $result[$nameMap[$gate]] = $authorizedTo->allowed();
                }
            }
        }

        return $result;
    }

    /**
     * Convert an Eloquent model into an array representation for the HTTP response.
     *
     * This method transforms the given model by selecting only the specified attributes and aggregates as defined in the request parameters or resource. If authorization gating is enabled and gate parameters are provided, it appends the corresponding authorization data. Additionally, it recursively processes any loaded relations—returning pivot data when applicable and mapping related models (or collections of models) using the resource’s configuration.
     *
     * @param  Model  $model  The Eloquent model instance to be converted.
     * @param  resource  $resource  The resource defining the fields and structure of the response.
     * @param  array  $requestArray  Request parameters that control field selection, aggregates, and authorization gates.
     * @param  Relation|null  $relation  Optional relation context for processing nested relationships.
     * @return array The structured array representation of the model, including attributes and recursively processed relations.
     */
    public function modelToResponse(Model $model, Resource $resource, array $requestArray, ?Relation $relation = null)
    {
        $aggregates = collect($requestArray['aggregates'] ?? [])
            ->map(function ($aggregate) {
                return $aggregate['alias'] ?? Str::snake($aggregate['relation']).'_'.$aggregate['type'].(isset($aggregate['field']) ? '_'.$aggregate['field'] : '');
            })
            ->toArray();
        $attributes = $model->attributesToArray();
        $columns = array_filter($attributes, fn (int|string $key): bool => ! in_array($key, $aggregates, true), ARRAY_FILTER_USE_KEY);

        return array_merge(
            // toArray to take advantage of Laravel's logic
            collect(array_replace($attributes, $resource->getFields($resource->context()->request)->fromStorage($columns)))
                ->only(
                    array_merge(
                        isset($requestArray['selects']) ?
                                collect($requestArray['selects'])->pluck('field')->toArray() :
                                $resource->getFields($resource->context()->request)->selectable()->names(),
                        $aggregates
                    )
                )
                ->when($resource->isGatingEnabled() && isset($requestArray['gates']), function ($attributes) use ($requestArray, $resource, $model) {
                    $currentRequestArrayWithoutCreate = collect($requestArray['gates'])->reject(fn ($value) => $value === 'create')->toArray();

                    return $attributes->put(
                        config('rest.gates.key'),
                        $this->buildGatesForModel($model, $resource, $currentRequestArrayWithoutCreate)
                    );
                })
                ->toArray(),
            collect($model->getRelations())
                ->mapWithKeys(function ($modelRelation, $relationName) use ($requestArray, $relation, $resource) {
                    $currentInclude = collect($requestArray['includes'] ?? [])
                        ->first(function ($include) use ($relationName) {
                            return ($include['alias'] ?? $include['relation']) === $relationName;
                        });

                    $realRelation = $currentInclude['relation'] ?? $relationName;
                    $key = $currentInclude['alias'] ?? Str::snake($realRelation);

                    if (is_null($modelRelation)) {
                        return [
                            $key => null,
                        ];
                    } elseif ($modelRelation instanceof Pivot) {
                        $pivotFields = $relation instanceof PivotRelation ? $relation->getPivotFields() : FieldSet::of([]);

                        return [
                            $key => collect($pivotFields->fromStorage($modelRelation->toArray()))
                                ->only($pivotFields->selectable()->names())
                                ->toArray(),
                        ];
                    }

                    $relationConcrete = $resource->relation($realRelation);
                    $relationResource = $relationConcrete->resource();
                    $nestedRequestArray = $this->requestArrayForRelation($requestArray, $currentInclude, $relationName);

                    if ($modelRelation instanceof Model) {
                        return [
                            $key => $this->modelToResponse(
                                $modelRelation,
                                $relationResource,
                                $nestedRequestArray,
                                $relationConcrete
                            ),
                        ];
                    }

                    return [
                        $key => $modelRelation
                            ->map(fn ($collectionRelation) => $this->modelToResponse($collectionRelation, $relationResource, $nestedRequestArray, $relationConcrete))
                            ->toArray(),
                    ];
                })
                ->toArray()
        );
    }

    /**
     * Build the request array a relation is rendered with.
     *
     * A dotted include declares its parameters for the deepest relation of the path, the same way
     * the query applies them, so every level above only forwards the remaining path downwards.
     *
     * @param  array  $requestArray  Request parameters of the level being rendered.
     * @param  array|null  $currentInclude  The include matching the relation, if any.
     * @param  string  $relationName  The name the relation is loaded under.
     * @return array The request parameters to render the relation with.
     */
    protected function requestArrayForRelation(array $requestArray, ?array $currentInclude, string $relationName): array
    {
        $forwardedIncludes = collect($requestArray['includes'] ?? [])
            ->filter(function ($include) use ($relationName) {
                return Str::contains($include['relation'], '.')
                    && Str::before($include['relation'], '.') === $relationName;
            })
            ->map(function ($include) {
                return array_merge($include, ['relation' => Str::after($include['relation'], '.')]);
            })
            ->values()
            ->all();

        $nestedRequestArray = $currentInclude ?? [];

        if (! empty($forwardedIncludes)) {
            $nestedRequestArray['includes'] = array_merge($nestedRequestArray['includes'] ?? [], $forwardedIncludes);
        }

        return $nestedRequestArray;
    }

    #[\Override]
    public function toResponse($request)
    {
        if ($this->responsable instanceof LengthAwarePaginator) {
            $restLengthAwarePaginator = new \Lomkit\Rest\Pagination\LengthAwarePaginator(
                $this->responsable->items(),
                $this->responsable->total(),
                $this->responsable->perPage(),
                $this->responsable->currentPage(),
                $this->responsable->getOptions(),
                $this->resource->isGatingEnabled() && in_array('create', $request->array('search.gates')) ? [
                    config('rest.gates.key') => $this->buildGatesForModel($this->resource::newModel()::class, $this->resource, ['create']),
                ] : []
            );

            $restLengthAwarePaginator->through(function ($model) use ($request) {
                return $this->map($model, $this->modelToResponse($model, $this->resource, $request->array('search')));
            });

            return $restLengthAwarePaginator;
        } elseif ($this->responsable instanceof Collection) {
            $data = $this->responsable->map(function ($model) use ($request) {
                return $this->map($model, $this->modelToResponse($model, $this->resource, $request->array('search')));
            });
        }

        return [
            'data' => $data ?? $this->map($this->responsable, $this->modelToResponse($this->responsable, $this->resource, $request->array('search'))),
            'meta' => array_merge(
                $this->resource->isGatingEnabled() && in_array('create', $request->array('search.gates')) ? [
                    config('rest.gates.key') => $this->buildGatesForModel($this->resource::newModel()::class, $this->resource, ['create']),
                ] : []
            ),
        ];
    }

    protected function map(Model $model, array $responseModel): array
    {
        return $responseModel;
    }

    // @TODO: this class needs a refactor because it has grown a lot since the beginning also with the "lengthAwarePaginator"
    // @TODO: recursive response for those gates ?
}
