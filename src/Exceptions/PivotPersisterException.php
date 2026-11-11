<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class PivotPersisterException extends LogicException
{
    /** @param list<int|string> $keys */
    public static function unattached(string $persister, string $resource, string $relation, array $keys): self
    {
        $keys = implode(', ', $keys);

        return new self("The pivot persister [{$persister}] attached [{$keys}] on the relation [{$relation}] of [{$resource}], but the relation does not link them. A pivot row is linked when the pivot conditions of the Eloquent relation select it.");
    }

    /** @param list<int|string> $keys */
    public static function undetached(string $persister, string $resource, string $relation, array $keys): self
    {
        $keys = implode(', ', $keys);

        return new self("The pivot persister [{$persister}] detached [{$keys}] on the relation [{$relation}] of [{$resource}], but the relation still links them. A withdrawn pivot row must fall outside the pivot conditions of the Eloquent relation, such as ->wherePivotNull('withdrawn_at').");
    }

    public static function notManyToMany(string $model, string $relation): self
    {
        return new self("The relation [{$relation}] of [{$model}] is not a BelongsToMany, MorphToMany or MorphedByMany relation.");
    }

    public static function noRelatedRows(string $relation): self
    {
        return new self("A pivot write on the relation [{$relation}] names no related row.");
    }
}
