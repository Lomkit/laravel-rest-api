<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;

class BelongsTo extends Relation implements RelationResource
{
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
