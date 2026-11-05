<?php

namespace Lomkit\Rest\Search\Plan;

use Illuminate\Support\Str;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Contracts\OwnerRelation;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\ResourceLimits;
use Lomkit\Rest\Query\Clauses\AggregateClause;
use Lomkit\Rest\Query\Clauses\Clause;
use Lomkit\Rest\Query\Clauses\FilterGroupClause;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\AggregateNode;
use Lomkit\Rest\Search\IncludeNode;
use Lomkit\Rest\Search\MorphIncludeNode;
use Lomkit\Rest\Search\Plan\Columns\ColumnSelection;
use Lomkit\Rest\Search\Plan\Columns\RelationKeys;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Text\ResolvedText;

final readonly class SearchPlanner
{
    public function __construct(
        private RowSource $rows,
        private ColumnSelection $columns,
    ) {}

    public static function paged(Resource $resource, SearchNode $search, ResolvedText $text): QueryPlan
    {
        return (new self(new SqlRows($text), ColumnSelection::of($resource)))->plan($resource, $search, LimitPolicy::paged($search->limit ?? self::limits($resource)->defaultLimit, $search->page ?? 1));
    }

    public static function all(Resource $resource, SearchNode $search, ResolvedText $text): QueryPlan
    {
        return (new self(new SqlRows($text), ColumnSelection::Every))->plan($resource, $search, LimitPolicy::explicit($search->limit));
    }

    public function plan(Resource $resource, SearchNode $search, LimitPolicy $limit): QueryPlan
    {
        return $this->level($resource, $search, $search->includes, $limit, []);
    }

    /**
     * @param  list<IncludeNode|MorphIncludeNode>  $includes
     * @param  list<string>  $keys
     */
    private function level(Resource $resource, SearchNode $search, array $includes, LimitPolicy $limit, array $keys): QueryPlan
    {
        $resource
            ->context()
            ->authorizer()
            ->ensurePlanned(Check::onClass($resource, 'viewAny'));

        $aggregates = array_map(self::aggregate(...), $search->aggregates, array_keys($search->aggregates));

        return new QueryPlan(
            $resource,
            [new PerimeterClause($resource), ...$this->rows->clauses($resource, $search, $aggregates)],
            $this->includes($resource, $includes),
            $aggregates,
            $limit,
            $search->selects,
            $search->gates,
            $this->columns->level($resource, $search->selects, self::names($includes), [...$keys, ...$this->rows->columns()]),
        );
    }

    /**
     * @param  list<IncludeNode|MorphIncludeNode>  $includes
     * @return list<Clause>
     */
    private function includes(Resource $parent, array $includes): array
    {
        $entries = [];
        $groups = [];

        foreach ($includes as $include) {
            if ($include->alias !== null) {
                $entries[] = $include instanceof MorphIncludeNode
                    ? $this->morph($parent, $include, [])
                    : new AliasedIncludeClause($include->relation, $include->alias, $this->explicit($parent, $include, []));

                continue;
            }

            $segment = Str::before($include->relation, '.');

            if (! array_key_exists($segment, $groups)) {
                $entries[] = $segment;
                $groups[$segment] = ['relation' => $include->relations[0], 'level' => null, 'children' => []];
            }

            $child = self::below($include);

            if ($child === null) {
                $groups[$segment]['level'] = $include;

                continue;
            }

            $groups[$segment]['children'][] = $child;
        }

        return array_map(
            fn (Clause|string $entry): Clause => is_string($entry) ? $this->grouped($parent, $entry, $groups[$entry]['relation'], $groups[$entry]['level'], $groups[$entry]['children']) : $entry,
            $entries,
        );
    }

    private static function below(IncludeNode|MorphIncludeNode $include): IncludeNode|MorphIncludeNode|null
    {
        if ($include instanceof MorphIncludeNode) {
            return $include->below();
        }

        $relations = array_slice($include->relations, 1);

        return $relations === [] ? null : new IncludeNode(Str::after($include->relation, '.'), $relations, $include->resource, null, $include->search);
    }

    /** @param list<IncludeNode|MorphIncludeNode> $children */
    private function grouped(Resource $parent, string $name, Relation $relation, IncludeNode|MorphIncludeNode|null $level, array $children): Clause
    {
        if ($level instanceof MorphIncludeNode) {
            return $this->morph($parent, $level, $children);
        }

        $plan = $this->group($parent, $relation, $level, $children);

        return $relation instanceof PolymorphicRelation ? MorphToIncludeClause::of($relation, null, array_fill_keys($relation->targets()->types(), $plan)) : new IncludeClause($name, $plan);
    }

    /** @param list<IncludeNode|MorphIncludeNode> $children */
    private function morph(Resource $parent, MorphIncludeNode $include, array $children): MorphToIncludeClause
    {
        return MorphToIncludeClause::of($include->morphTo, $include->alias, array_map(fn (IncludeNode $branch): QueryPlan => $this->explicit($parent, $branch, $children), $include->branches));
    }

    /** @param list<IncludeNode|MorphIncludeNode> $children */
    private function group(Resource $parent, Relation $relation, ?IncludeNode $level, array $children): QueryPlan
    {
        if ($level === null) {
            $resource = $relation->resource();
            $columns = $this->columns->level($resource, null, self::names($children), RelationKeys::between($parent::newModel(), $relation->relation)->related());

            return $relation instanceof OwnerRelation
                ? new QueryPlan($resource, [], $this->includes($resource, $children), [], LimitPolicy::none(), columns: $columns)
                : new QueryPlan($resource, [new DefaultOrderClause($resource)], $this->includes($resource, $children), [], self::includeLimit($resource, null), columns: $columns);
        }

        return $this->explicit($parent, $level, $children);
    }

    /** @param list<IncludeNode|MorphIncludeNode> $children */
    private function explicit(Resource $parent, IncludeNode $include, array $children): QueryPlan
    {
        $owner = $include->relations[0] instanceof OwnerRelation;

        return (new self(new SqlRows(null, ! $owner), $this->columns))->level($include->resource, $include->search, [...$children, ...$include->search->includes], $owner ? LimitPolicy::none() : self::includeLimit($include->resource, $include->search->limit), RelationKeys::between($parent::newModel(), $include->relations[0]->relation)->related());
    }

    /**
     * @param  list<IncludeNode|MorphIncludeNode>  $includes
     * @return list<string>
     */
    private static function names(array $includes): array
    {
        return array_map(static fn (IncludeNode|MorphIncludeNode $include): string => Str::before($include->relation, '.'), $includes);
    }

    private static function includeLimit(Resource $resource, ?int $limit): LimitPolicy
    {
        return LimitPolicy::explicit($limit ?? self::limits($resource)->includeLimit);
    }

    private static function limits(Resource $resource): ResourceLimits
    {
        return $resource->definition()->limits();
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
