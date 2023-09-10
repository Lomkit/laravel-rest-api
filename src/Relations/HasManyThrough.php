<?php

namespace Lomkit\Rest\Relations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Traits\HasMultipleResults;

class HasManyThrough extends Relation implements RelationResource
{
    use HasMultipleResults;

    public function afterMutating(Model $model, Relation $relation, array $mutationRelations)
    {
        throw new \RuntimeException('You can\'t mutate a \'HasManyThrough\' relation.');
    }

    public function rules(Resource $resource, string $prefix)
    {
        return [
            $prefix => 'prohibited',
        ];
    }
}
