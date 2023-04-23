<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Concerns\Relations\HasPivotFields;
use Lomkit\Rest\Contracts\RelationResource;

class BelongsToMany extends Relation implements RelationResource
{
    use HasPivotFields;
}