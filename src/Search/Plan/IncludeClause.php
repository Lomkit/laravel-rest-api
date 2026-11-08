<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Query\ModelRelation;

final readonly class IncludeClause implements Clause
{
    public function __construct(
        public string $relation,
        public QueryPlan $plan,
        private bool $textKeys = false,
    ) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        if ($this->textKeys) {
            $query->afterQuery((new TextKeyLoad(new ModelRelation($this->relation, true), $this->plan))->load(...));

            return;
        }

        $query->with($this->relation, function (Relation $relation): void {
            $this->plan->apply($relation);
        });
    }
}
