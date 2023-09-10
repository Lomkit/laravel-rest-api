<?php

namespace Lomkit\Rest\Relations\Traits;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Relations\Relation;

trait Mutates
{
    public function beforeMutating(Model $model, Relation $relation, array $mutationRelations)
    {
    }

    public function afterMutating(Model $model, Relation $relation, array $mutationRelations)
    {
    }
}
