<?php

namespace Lomkit\Rest\Search\Plan;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\AggregateClause;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Query\Clauses\FilterGroupClause;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Search\AggregateNode;
use Lomkit\Rest\Search\IncludeNode;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\ResolvedText;

final readonly class SearchPlanner
{
    public function __construct(private RowSource $rows) {}

    public static function paged(Resource $resource, SearchNode $search, ResolvedText $text): QueryPlan
    {
        return (new self(new SqlRows($text)))->plan($resource, $search, LimitPolicy::paged($search->limit ?? $resource->defaultLimit, $search->page ?? 1));
    }

    public static function all(Resource $resource, SearchNode $search, ResolvedText $text): QueryPlan
    {
        return (new self(new SqlRows($text)))->plan($resource, $search, LimitPolicy::explicit($search->limit));
    }

    public function plan(Resource $resource, SearchNode $search, LimitPolicy $limit): QueryPlan
    {
        $resource
            ->context()
            ->authorizer()
            ->ensurePlanned(Check::onClass($resource, 'viewAny'));

        return new QueryPlan(
            $resource,
            [new PerimeterClause($resource), ...$this->rows->clauses($resource, $search)],
            array_map(self::include(...), $search->includes),
            array_map(self::aggregate(...), $search->aggregates),
            $limit,
        );
    }

    private static function include(IncludeNode $include): Clause
    {
        $plan = (new self(new SqlRows(null)))->plan($include->resource, $include->search, LimitPolicy::explicit($include->search->limit));

        if ($include->alias === null) {
            return new IncludeClause($include->relation, $plan);
        }

        return new AliasedIncludeClause($include->relation, $include->alias, $plan);
    }

    private static function aggregate(AggregateNode $aggregate): AggregateClause
    {
        $clauses = [new PerimeterClause($aggregate->resource)];

        if ($aggregate->filters->filters !== []) {
            $clauses[] = FilterGroupClause::of($aggregate->filters);
        }

        return AggregateClause::of($aggregate, new QueryPlan($aggregate->resource, $clauses, [], [], LimitPolicy::none()));
    }
}
