<?php

namespace Lomkit\Rest\Tests\Feature\Query\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Query\Clauses\Clause;

final readonly class MinimumViewsClause implements Clause
{
    #[\Override]
    public function apply(Builder $query): void
    {
        $query->where('views', '>', 10);
    }
}
