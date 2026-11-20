<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;

/** @internal */
final readonly class NoRowsClause implements Clause
{
    #[\Override]
    public function apply(Builder $query): void
    {
        $query->whereRaw('0 = 1');
    }
}
