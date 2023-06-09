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

    public function responsable($responsable) {
        return tap($this, function () use ($responsable) {
            $this->responsable = $responsable;
        });
    }

    public function resource(Resource $resource) {
        return tap($this, function () use ($resource) {
            $this->resource = $resource;
        });
    }

    public function modelToResponse(Model $model, Resource $resource, array $requestArray, Relation $relation = null) {
        return array_merge(
            // toArray to take advantage of Laravel's logic
            collect($model->attributesToArray())
                ->only(
                    isset($requestArray['selects']) ?
                        collect($requestArray['selects'])->pluck('field') :
                        $resource->exposedFields(app()->make(RestRequest::class))
                )
                ->toArray(),
            collect($model->getRelations())
                ->mapWithKeys(function ($modelRelation, $relationName) use ($requestArray, $relation, $resource) {
                    $key = Str::snake($relationName);

                    if (is_null($modelRelation)) {
                        return [
                            $key => null
                        ];
                    } elseif ($modelRelation instanceof Pivot) {
                        return [
                            $key => collect($modelRelation->toArray())
                                ->only($relation->getPivotFields())
                                ->toArray()
                        ];
                    }

                    $relationConcrete =  $resource->relation($relationName);
                    $relationResource = $relationConcrete->resource();
                    $requestArrayRelation = collect($requestArray['includes'])
                        ->first(function ($include) use ($relationName) {
                            return $include['relation'] === $relationName;
                        });

                    // We reapply the limits in case of BelongsToManyRelation where we can't apply limits easily
                    if ($modelRelation instanceof Collection) {
                        $modelRelation = $modelRelation->take($requestArrayRelation['limit'] ?? 50);
                    } else if ($modelRelation instanceof Model) {
                        return [
                            $key => $this->modelToResponse(
                                $modelRelation,
                                $relationResource,
                                $requestArrayRelation,
                                $relationConcrete
                            )
                        ];
                    }
                    return [
                        $key => $modelRelation
                            ->map(fn ($collectionRelation) => $this->modelToResponse($collectionRelation, $relationResource, $requestArrayRelation, $relationConcrete))
                            ->toArray()
                    ];
                })
                ->toArray()
        );
    }

    public function toResponse($request) {
        if ($this->responsable instanceof LengthAwarePaginator) {
            return $this->responsable->through(function ($model) use ($request) {
                return $this->map($model, $this->modelToResponse($model, $this->resource, $request->input()));
            });
        }

        return $this->map($this->responsable, [
            'data' => $this->modelToResponse($this->responsable, $this->resource, $request->input())
        ]);
    }

    protected function map(Model $model, array $response) {
        return $response;
    }
}