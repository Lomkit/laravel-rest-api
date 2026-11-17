<?php

namespace Lomkit\Rest\Text\Engine\Groups;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Text\Engine\EngineFilterCondition;

/** @internal */
final readonly class FilterLeaf
{
    /** @param int|float|string|bool|list<int|float|string|bool> $value */
    public function __construct(
        public string $attribute,
        public FilterOperator $operator,
        public int|float|string|bool|array $value,
        public bool $list = false,
    ) {}

    public static function of(EngineFilterCondition $condition): self
    {
        $declared = $condition->attribute;

        return match ($condition->operator) {
            FilterOperator::In, FilterOperator::NotIn => new self($condition->name, $condition->operator, $declared->members($condition->operator, is_array($condition->value) ? $condition->value : throw InvalidEngineQueryException::invalidValue($condition->name, $condition->value))),
            default => new self($condition->name, $declared->comparison($condition->operator), $declared->value($condition->value)),
        };
    }

    /** @return list<int|float|string|bool> */
    public function members(): array
    {
        return is_array($this->value) ? $this->value : [$this->value];
    }

    public function scalar(): int|float|string|bool
    {
        return is_array($this->value) ? throw InvalidEngineQueryException::invalidValue($this->attribute, $this->value) : $this->value;
    }
}
