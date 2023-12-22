<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Resource;

class MorphTo extends MorphRelation implements RelationResource
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
        $model
            ->{$relation->relation}()
            ->{$mutationRelations[$relation->relation]['operation'] === 'detach' ? 'dissociate' : 'associate'}(
                app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
                    ->applyMutation($mutationRelations[$relation->relation])
            );
    }
}
