<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\AggregateClause;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Search\GateAbility;
use Lomkit\Rest\Search\Plan\Columns\ColumnSet;

final readonly class QueryPlan implements Clause
{
    /**
     * @param  list<Clause>  $clauses
     * @param  list<Clause>  $includes
     * @param  list<AggregateClause>  $aggregates
     * @param  list<string>|null  $selects
     * @param  list<GateAbility>|null  $gates
     */
    public function __construct(
        public Resource $resource,
        public array $clauses,
        public array $includes,
        public array $aggregates,
        public LimitPolicy $limit,
        public ?array $selects = null,
        public ?array $gates = null,
        public ?ColumnSet $columns = null,
    ) {}

    public static function bare(Resource $resource): self
    {
        return new self($resource, [], [], [], LimitPolicy::none());
    }

    public function keys(): self
    {
        return new self($this->resource, $this->clauses, [], [], LimitPolicy::none());
    }

    /** @return EloquentBuilder<Model> */
    public function query(): EloquentBuilder
    {
        $query = $this->resource::newModel()->query();
        $this->apply($query);

        return $query;
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        foreach ($this->clauses as $clause) {
            $clause->apply($query);
        }

        $this->columns?->apply($query);

        foreach ([...$this->includes, ...$this->aggregates] as $clause) {
            $clause->apply($query);
        }

        $this->limit->apply($query);
    }
}
