<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Support\Str;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\ResourceLimits;
use Lomkit\Rest\Query\Clauses\AggregateClause;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Query\Clauses\FilterGroupClause;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\AggregateNode;
use Lomkit\Rest\Search\IncludeNode;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\ResolvedText;

final readonly class SearchPlanner
{
    public function __construct(private RowSource $rows) {}

    public static function paged(Resource $resource, SearchNode $search, ResolvedText $text): QueryPlan
    {
        return (new self(new SqlRows($text)))->plan($resource, $search, LimitPolicy::paged($search->limit ?? self::limits($resource)->defaultLimit, $search->page ?? 1));
    }

    public static function all(Resource $resource, SearchNode $search, ResolvedText $text): QueryPlan
    {
        return (new self(new SqlRows($text)))->plan($resource, $search, LimitPolicy::explicit($search->limit));
    }

    public function plan(Resource $resource, SearchNode $search, LimitPolicy $limit): QueryPlan
    {
        return $this->level($resource, $search, $search->includes, $limit);
    }

    /** @param list<IncludeNode> $includes */
    private function level(Resource $resource, SearchNode $search, array $includes, LimitPolicy $limit): QueryPlan
    {
        $resource
            ->context()
            ->authorizer()
            ->ensurePlanned(Check::onClass($resource, 'viewAny'));

        $aggregates = array_map(self::aggregate(...), $search->aggregates, array_keys($search->aggregates));

        return new QueryPlan(
            $resource,
            [new PerimeterClause($resource), ...$this->rows->clauses($resource, $search, $aggregates)],
            self::includes($includes),
            $aggregates,
            $limit,
            $search->selects,
            $search->gates,
        );
    }

    /**
     * @param  list<IncludeNode>  $includes
     * @return list<Clause>
     */
    private static function includes(array $includes): array
    {
        $entries = [];
        $groups = [];

        foreach ($includes as $include) {
            if ($include->alias !== null) {
                $entries[] = $include->relations[0] instanceof MorphTo
                    ? MorphToIncludeClause::of($include->relations[0], $include->alias, self::explicit($include, []))
                    : new AliasedIncludeClause($include->relation, $include->alias, self::explicit($include, []));

                continue;
            }

            $segment = Str::before($include->relation, '.');

            if (! array_key_exists($segment, $groups)) {
                $entries[] = $segment;
                $groups[$segment] = ['relation' => $include->relations[0], 'level' => null, 'children' => []];
            }

            $relations = array_slice($include->relations, 1);

            if ($relations === []) {
                $groups[$segment]['level'] = $include;

                continue;
            }

            $groups[$segment]['children'][] = new IncludeNode(Str::after($include->relation, '.'), $relations, $include->resource, null, $include->search);
        }

        return array_map(
            static fn (Clause|string $entry): Clause => is_string($entry) ? self::grouped($entry, $groups[$entry]['relation'], self::group($groups[$entry]['relation'], $groups[$entry]['level'], $groups[$entry]['children'])) : $entry,
            $entries,
        );
    }

    private static function grouped(string $name, Relation $relation, QueryPlan $plan): Clause
    {
        return $relation instanceof MorphTo ? MorphToIncludeClause::of($relation, null, $plan) : new IncludeClause($name, $plan);
    }

    /** @param list<IncludeNode> $children */
    private static function group(Relation $relation, ?IncludeNode $level, array $children): QueryPlan
    {
        if ($level === null) {
            return new QueryPlan($relation->resource(), [], self::includes($children), [], LimitPolicy::none());
        }

        return self::explicit($level, $children);
    }

    /** @param list<IncludeNode> $children */
    private static function explicit(IncludeNode $include, array $children): QueryPlan
    {
        $owner = $include->relations[0] instanceof MorphTo;

        return (new self(new SqlRows(null, ! $owner)))->level($include->resource, $include->search, [...$children, ...$include->search->includes], $owner ? LimitPolicy::none() : LimitPolicy::explicit($include->search->limit));
    }

    private static function limits(Resource $resource): ResourceLimits
    {
        return $resource->context()
            ->definition($resource)
            ->limits();
    }

    private static function aggregate(AggregateNode $aggregate, int $position): AggregateClause
    {
        $clauses = [new PerimeterClause($aggregate->resource)];

        if ($aggregate->filters->filters !== []) {
            $clauses[] = FilterGroupClause::of($aggregate->filters);
        }

        return AggregateClause::of($aggregate, new QueryPlan($aggregate->resource, $clauses, [], [], LimitPolicy::none()), $position);
    }
}
