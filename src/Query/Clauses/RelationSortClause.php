<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\RelationAggregate;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Search\RelationSort;

final readonly class RelationSortClause implements Clause
{
    public function __construct(
        private Relation $relation,
        private Field $field,
        private SortDirection $direction,
    ) {}

    public static function of(RelationSort $sort): self
    {
        return new self($sort->relation, $sort->field, $sort->direction());
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        (new RelationAggregate($this->relation->relation, AggregateFunction::Max, $this->field))->order($query, $this->perimeter(...), $this->direction);
    }

    /** @param EloquentBuilder<Model> $query */
    private function perimeter(EloquentBuilder $query): void
    {
        $this->relation->applySearchQuery($query);
    }
}
