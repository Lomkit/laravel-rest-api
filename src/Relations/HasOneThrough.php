<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Resource;

class HasOneThrough extends Relation implements RelationResource
{
    public function afterMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        throw new \RuntimeException('You can\'t mutate a \'HasOneThrough\' relation.');
    }

    public function rules(Resource $resource, string $prefix)
    {
        return [
            $prefix => 'prohibited',
        ];
    }
}
