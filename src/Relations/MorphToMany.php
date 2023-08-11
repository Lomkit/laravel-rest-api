<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Concerns\Relations\HasPivotFields;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\HasMultipleResults;

class MorphToMany extends MorphRelation implements RelationResource
{
    use HasPivotFields, HasMultipleResults;

    public function rules(Resource $resource, string $prefix)
    {
        return array_merge(
            parent::rules($resource, $prefix),
            [
                $prefix.'.*.pivot' => [
                    'prohibited_if:'.$prefix.'.*.operation,detach'
                ],
                $prefix.'.*.pivot.*.field' => [
                    'required_with:'.$prefix.'.*.pivot.*',
                    Rule::in($this->getPivotFields())
                ],
                $prefix.'.*.pivot.*.value' => [
                    'required_with:'.$prefix.'.*.pivot.*'
                ]
            ]
        );
    }

    public function afterMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        foreach ($mutationRelations[$relation->relation] as $mutationRelation) {
            if ($mutationRelation['operation'] === 'detach') {
                $model
                    ->{$relation->relation}()
                    ->detach(
                        app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
                            ->applyMutation($mutationRelation)
                            ->getKey()
                    );
            } else {
                $model
                    ->{$relation->relation}()
                    ->attach(
                        [
                            app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
                                ->applyMutation($mutationRelation)
                                ->getKey()
                            =>
                                collect($mutationRelation['pivot'] ?? [])
                                    ->mapWithKeys(function ($pivot) {
                                        return [$pivot['field'] => $pivot['value']];
                                    })
                                    ->toArray()
                        ]
                    );
            }
        }
    }
}