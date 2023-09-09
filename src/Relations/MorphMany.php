<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Relations\Traits\HasMultipleResults;

class MorphMany extends MorphRelation implements RelationResource
{
    use HasMultipleResults;

    /**
     * Handle actions after mutating a MorphOne relation.
     *
     * @param Model    $model             The Eloquent model.
     * @param Relation $relation          The relation being mutated.
     * @param array    $mutationRelations An array of mutation relations.
     */
    public function afterMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        foreach ($mutationRelations[$relation->relation] as $mutationRelation) {
            $attributes = [
                $model->{$relation->relation}()->getForeignKeyName() => $mutationRelation['operation'] === 'detach' ? null : $model->{$relation->relation}()->getParentKey(),
                $model->{$relation->relation}()->getMorphType()      => $model::class,
            ];

            app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
                ->applyMutation($mutationRelation, $attributes);
        }
    }
}
