<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Search\FieldSort;

final readonly class SortClause implements Clause
{
    public function __construct(
        private Field $field,
        private SortDirection $direction = SortDirection::Asc,
    ) {}

    public static function of(FieldSort $sort): self
    {
        return new self($sort->field, $sort->direction());
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $this->field->sort($query instanceof Relation ? $query->getQuery() : $query, $this->direction);
    }
}
