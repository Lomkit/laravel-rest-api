<?php

namespace Lomkit\Rest\Search\Plan;

use Lomkit\Rest\Query\Clauses\AggregateClause;
use Lomkit\Rest\Query\Clauses\FilterGroupClause;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Search\AggregateNode;

/** @internal */
final readonly class AggregatePlan
{
    public static function of(AggregateNode $aggregate, int $position): AggregateClause
    {
        $clauses = [new PerimeterClause($aggregate->resource)];

        if ($aggregate->filters->filters !== []) {
            $clauses[] = FilterGroupClause::of($aggregate->filters);
        }

        return AggregateClause::of($aggregate, new QueryPlan($aggregate->resource, $clauses, [], [], LimitPolicy::none()), $position);
    }
}
