<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;

class BelongsTo extends Relation implements RelationResource
{
    /**
     * Perform actions before mutating the MorphTo relation.
     *
     * @param Model    $model             The Eloquent model.
     * @param Relation $relation          The relation being mutated.
     * @param array    $mutationRelations An array of mutation relations.
     */
    public function beforeMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        $toPerformActionModel = app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
            ->applyMutation($mutationRelations[$relation->relation]);

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

        $model
            ->{$relation->relation}()
            ->{$mutationRelations[$relation->relation]['operation'] === 'detach' ? 'dissociate' : 'associate'}(
                $toPerformActionModel
            );
    }
}
