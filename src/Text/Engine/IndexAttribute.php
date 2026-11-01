<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

final readonly class IndexAttribute
{
    /** @param list<FilterOperator> $operators */
    public function __construct(
        public Field $field,
        public array $operators,
    ) {}

    public function supports(FilterOperator $operator): bool
    {
        return in_array($operator, $this->operators, true);
    }

    public function value(mixed $value): int|float|string|bool
    {
        return EngineValue::of($this->field, $value) ?? throw InvalidEngineQueryException::invalidValue($this->field->name, $value);
    }
}
