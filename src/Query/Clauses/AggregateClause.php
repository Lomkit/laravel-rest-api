<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\RelationAggregate;
use Lomkit\Rest\Search\AggregateKey;
use Lomkit\Rest\Search\AggregateNode;

final readonly class AggregateClause implements Clause
{
    private const string LABEL = '@aggregate_';

    private function __construct(
        private RelationAggregate $aggregate,
        public AggregateKey $key,
        public string $label,
        private Clause $constraint,
    ) {}

    public static function of(AggregateNode $node, Clause $constraint, int $position): self
    {
        return new self(new RelationAggregate($node->relation, $node->function, $node->field), $node->key, self::LABEL.$position, $constraint);
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
