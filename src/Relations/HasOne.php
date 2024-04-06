<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;

class HasOne extends Relation implements RelationResource
{
    /**
     * Perform actions after mutating the HasOne relation.
     *
     * @param Model    $model             The Eloquent model.
     * @param Relation $relation          The relation being mutated.
     * @param array    $mutationRelations An array of mutation relations.
     */
    public function afterMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        $attributes = [
            $model->{$relation->relation}()->getForeignKeyName() => $mutationRelations[$relation->relation]['operation'] === 'detach' ? null : $model->{$relation->relation}()->getParentKey(),
        ];

        $toPerformActionModel = app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
            ->applyMutation($mutationRelations[$relation->relation], $attributes);

        switch ($mutationRelations[$relation->relation]['operation']) {
            case 'create':
            case 'update':
            case 'attach':
                $this->resource()->authorizeToAttach($model, $toPerformActionModel);
                break;
            case 'detach':
                $this->resource()->authorizeToDetach($model, $toPerformActionModel);
                break;
        }
    }
}
