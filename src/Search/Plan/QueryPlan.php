<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\AggregateClause;
use Lomkit\Rest\Query\Clauses\Clause;

final readonly class QueryPlan implements Clause
{
    /**
     * @param  list<Clause>  $clauses
     * @param  list<Clause>  $includes
     * @param  list<AggregateClause>  $aggregates
     * @param  list<string>|null  $selects
     * @param  list<string>|null  $gates
     */
    public function __construct(
        public Resource $resource,
        public array $clauses,
        public array $includes,
        public array $aggregates,
        public LimitPolicy $limit,
        public ?array $selects = null,
        public ?array $gates = null,
    ) {}

    public static function bare(Resource $resource): self
    {
        return new self($resource, [], [], [], LimitPolicy::none());
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        foreach ([...$this->clauses, ...$this->includes, ...$this->aggregates] as $clause) {
            $clause->apply($query);
        }

        $this->limit->apply($query);
    }
}
