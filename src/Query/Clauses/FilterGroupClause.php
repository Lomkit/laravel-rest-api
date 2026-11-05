<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use LogicException;
use Lomkit\Rest\Search\FilterCondition;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\FilterNode;
use Lomkit\Rest\Search\MorphFilterGroup;
use Lomkit\Rest\Search\RelationFilterGroup;

final readonly class FilterGroupClause implements Clause
{
    /** @param list<Clause> $nodes */
    public function __construct(
        private array $nodes,
        private string $boolean = 'and',
    ) {}

    public static function of(FilterGroup $group): self
    {
        return new self(array_map(self::clause(...), $group->filters), $group->boolean());
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            foreach ($this->nodes as $node) {
                $node->apply($query);
            }
        }, null, null, $this->boolean);
    }

    private static function clause(FilterNode $node): Clause
    {
        return match (true) {
            $node instanceof FilterGroup => self::of($node),
            $node instanceof RelationFilterGroup => RelationGroupClause::of($node),
            $node instanceof MorphFilterGroup => MorphGroupClause::of($node),
            $node instanceof FilterCondition && $node->path->relations === [] => new FieldConditionClause($node->path, $node->operator, $node->value, $node->boolean()),
            $node instanceof FilterCondition => new RelationConditionClause($node->path, $node->operator, $node->value, $node->boolean()),
            default => throw new LogicException($node::class.' does not apply to a database query.'),
        };
    }
}
