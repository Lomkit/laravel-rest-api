<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Contracts\RelationResource;

class MorphTo extends MorphRelation implements RelationResource
{
    public function __construct($relation, array $types)
    {
        $this->relation = $relation;
        $this->types = $types;
    }
}