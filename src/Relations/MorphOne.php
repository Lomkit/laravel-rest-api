<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;

class MorphOne extends MorphRelation implements RelationResource
{
    public function afterMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        $attributes = [
            $model->{$relation->relation}()->getForeignKeyName() => $mutationRelations[$relation->relation]['operation'] === 'detach' ? null : $model->{$relation->relation}()->getParentKey(),
            $model->{$relation->relation}()->getQualifiedMorphType() => $model::class
        ];

        app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
            ->applyMutation($mutationRelations[$relation->relation], $attributes);
    }
}