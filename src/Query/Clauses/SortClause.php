<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\SortDirection;

final readonly class SortClause implements Clause
{
    public function __construct(
        private Field $field,
        private SortDirection $direction = SortDirection::Asc,
    ) {}

    /** @param array{field: string, direction?: SortDirection|string|null} $sort */
    public static function fromSort(FieldSet $fields, array $sort): self
    {
        $direction = $sort['direction'] ?? null;

        return new self($fields->get($sort['field']), $direction === null ? SortDirection::Asc : SortDirection::parse($direction));
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        $this->field->sort($query instanceof Relation ? $query->getQuery() : $query, $this->direction);
    }
}
