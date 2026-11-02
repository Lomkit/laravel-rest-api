<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Query\Clauses\Clause;

final readonly class IncludeClause implements Clause
{
    public function __construct(
        public string $relation,
        public QueryPlan $plan,
    ) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        $query->with($this->relation, function (Relation $relation): void {
            $this->plan->apply($relation);
        });
    }
}
