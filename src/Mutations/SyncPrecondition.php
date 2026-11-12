<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use LogicException;
use Lomkit\Rest\Exceptions\SyncConflict;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Relations\Relation;

final readonly class SyncPrecondition
{
    /**
     * @param  HasOneOrMany<Model, Model, mixed>  $children
     * @param  list<int|string>  $accounted
     */
    public static function verify(Relation $relation, HasOneOrMany $children, MutationNode $sync, array $accounted): void
    {
        $parent = $children->getParent();

        if (! $parent->exists) {
            return;
        }

        $parent
            ->newQueryWithoutScopes()
            ->whereKey($parent->getKey())
            ->lockForUpdate()
            ->toBase()
            ->get([$parent->getKeyName()]);

        $key = $children->getRelated()->getQualifiedKeyName();
        (new PerimeterClause($relation->resource()))->apply($children);

        if ($accounted !== []) {
            $children->whereNotIn($key, $accounted);
        }

        $child = $children
            ->orderBy($key)
            ->lockForUpdate()
            ->first();

        if ($child !== null) {
            throw new SyncConflict($sync->path.'.key', self::key($child));
        }
    }

    private static function key(Model $child): int|string
    {
        $key = $child->getKey();

        if (! is_int($key) && ! is_string($key)) {
            throw new LogicException($child::class.' has a key that cannot name a related row.');
        }

        return $key;
    }
}
