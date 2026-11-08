<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionObject;

trait CopiesRelation
{
    /** @param Relation<*, *, *> $relation */
    public static function copy(Relation $relation): static
    {
        $copy = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();

        foreach ((new ReflectionObject($relation))->getProperties() as $property) {
            if (! $property->isStatic()) {
                $property->setValue($copy, $property->getValue($relation));
            }
        }

        return $copy;
    }
}
