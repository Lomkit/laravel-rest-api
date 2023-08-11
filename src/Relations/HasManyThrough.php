<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Resource;

class HasManyThrough extends Relation implements RelationResource
{
    public function afterMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        throw new \RuntimeException('You can\'t mutate a \'HasManyThrough\' relation.');
    }

    public function rules(Resource $resource, string $prefix)
    {
        return [
            $prefix => 'prohibited'
        ];
    }
}