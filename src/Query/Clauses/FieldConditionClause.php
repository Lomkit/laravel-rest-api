<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

final readonly class FieldConditionClause implements Clause
{
    public function __construct(
        private FieldPath $path,
        private FilterOperator $operator,
        private mixed $value,
        private string $boolean = 'and',
    ) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        $this->path->field->filter($query, $this->operator, $this->value, $this->boolean, $this->path->column($query->getModel()));
    }
}
