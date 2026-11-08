<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Lomkit\Rest\Query\Clauses\Clause;

final readonly class KeyOrderClause implements Clause
{
    #[\Override]
    public function apply(Builder $query): void
    {
        $base = match (true) {
            $query instanceof EloquentBuilder => $query,
            $query instanceof Relation => $query->getQuery(),
            default => throw new InvalidArgumentException('A key order applies to an Eloquent builder or relation, not to '.get_debug_type($query).'.'),
        };
        $key = $base->getModel()->getQualifiedKeyName();
        $columns = array_column($base->getQuery()->orders ?? [], 'column');

        if (in_array($key, $columns, true) || in_array($base->getModel()->getKeyName(), $columns, true)) {
            return;
        }

        $base->orderBy($key, 'desc');
    }
}
