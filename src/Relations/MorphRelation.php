<?php

namespace Lomkit\Rest\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lomkit\Rest\Contracts\RelationResource;
use Lomkit\Rest\Concerns\Makeable;

class MorphRelation extends Relation
{
    use Makeable;

    public function __construct($relation, $types)
    {
        $this->relation = $relation;
        $this->types = $types;
    }

    // @TODO: handle morphs in general
//    public function filter(Builder $query, $relation, $operator, $value, $boolean = 'and', Closure $callback = null)
//    {
//        return $query->hasMorph($query->getModel()->getTable().'.'.Str::beforeLast($relation, '.'), $this->types, '>=', 1, $boolean, function (Builder $query) use ($value, $operator, $relation, $callback) {
//            $query->where(Str::afterLast($relation, '.'), $operator, $value);
//            $callback($query);
//        });
//    }
}