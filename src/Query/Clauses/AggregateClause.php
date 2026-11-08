<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\AggregateQuery;
use Lomkit\Rest\Query\ModelRelation;
use Lomkit\Rest\Query\NestedAggregate;
use Lomkit\Rest\Query\RelationAggregate;
use Lomkit\Rest\Search\AggregateKey;
use Lomkit\Rest\Search\AggregateNode;

final readonly class AggregateClause implements Clause
{
    private const string LABEL = '@aggregate_';

    private function __construct(
        private AggregateQuery $aggregate,
        public AggregateKey $key,
        public string $label,
        private Clause $constraint,
    ) {}

    public static function of(AggregateNode $node, Clause $constraint, int $position): self
    {
        $relation = $node->last === null ? new ModelRelation(Str::afterLast($node->relation, '.')) : ModelRelation::of($node->last);
        $aggregate = $node->through === []
            ? new RelationAggregate($relation, $node->function, $node->field)
            : new NestedAggregate($node->through, $relation, $node->resource::newModel(), $node->function, $node->field);

        return new self($aggregate, $node->key, self::LABEL.$position, $constraint);
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $this->aggregate->select($query, $this->label, $this->constraint->apply(...));
    }

    public function order(Builder $query, SortDirection $direction): void
    {
        $this->aggregate->order($query, $this->constraint->apply(...), $direction);
    }
}
