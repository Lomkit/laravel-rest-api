<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use LogicException;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Search\AggregateSort;

final readonly class AggregateSortClause implements Clause
{
    public function __construct(
        private AggregateClause $aggregate,
        private SortDirection $direction,
    ) {}

    /** @param list<AggregateClause> $aggregates */
    public static function of(AggregateSort $sort, array $aggregates): self
    {
        foreach ($aggregates as $aggregate) {
            if ($aggregate->key->value === $sort->key->value) {
                return new self($aggregate, $sort->direction());
            }
        }

        throw new LogicException("The aggregate [{$sort->key->value}] is not planned at the level of its sort.");
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $this->aggregate->order($query, $this->direction);
    }
}
