<?php

namespace Lomkit\Rest\Text\Engine;

use JsonSerializable;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

final readonly class IndexAttribute implements JsonSerializable
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

    /** @return array{name: string, type: string, operators: list<string>, values?: list<int|string>} */
    #[\Override]
    public function jsonSerialize(): array
    {
        $details = [
            'name' => $this->field->name,
            'type' => $this->field->type(),
            'operators' => array_map(fn (FilterOperator $operator): string => $operator->value, $this->operators),
        ];

        if (! $this->field instanceof Enum) {
            return $details;
        }

        return [...$details, 'values' => $this->field->values()];
    }
}
