<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;

class HasOne extends Relation implements RelationResource
{
    public function beforeMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        $attributes = [
            $model->{$relation->relation}()->getForeignKeyName() => $mutationRelations[$relation->relation]['operation'] === 'detach' ? null : $model->{$relation->relation}()->getParentKey()
        ];

        app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
            ->applyMutation($mutationRelations[$relation->relation], $attributes);
    }
}