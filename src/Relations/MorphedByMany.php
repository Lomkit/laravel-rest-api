<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Concerns\Relations\HasPivotFields;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;

class MorphedByMany extends MorphRelation implements RelationResource
{
    use HasPivotFields;

    public function afterMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        dd('after mutating');
//        foreach ($mutationRelations[$relation->relation] as $mutationRelation) {
//            $model
//                ->{$relation->relation}()
//                ->{$mutationRelation['operation'] === 'detach' ? 'detach' : 'attach'}(
//                    app()->make(QueryBuilder::class, ['resource' => $relation->resource()])
//                        ->applyMutation($mutationRelation)
//                );
//        }
    }
}