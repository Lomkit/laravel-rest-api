<?php

namespace Lomkit\Rest\Search\Plan;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\AggregateClause;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Search\SearchNode;

interface RowSource
{
    /**
     * @param  list<AggregateClause>  $aggregates
     * @return list<Clause>
     */
    public function clauses(Resource $resource, SearchNode $search, array $aggregates = []): array;

    /** @return list<string> */
    public function columns(): array;
}
