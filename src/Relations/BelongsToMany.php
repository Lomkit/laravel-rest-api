<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Lomkit\Rest\Concerns\Relations\HasPivotFields;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\HasMultipleResults;

class BelongsToMany extends Relation implements RelationResource
{
    use HasPivotFields;
    use HasMultipleResults;

    public function rules(Resource $resource, string $prefix)
    {
        return array_merge(
            parent::rules($resource, $prefix),
            [
                $prefix.'.*.pivot' => [
                    'prohibited_if:'.$prefix.'.*.operation,detach',
                    'array:'.Arr::join($this->getPivotFields(), ','),
                ],
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
                                ->getKey() => $mutationRelation['pivot'] ?? [],
                        ]
                    );
            }
        }
    }
}
