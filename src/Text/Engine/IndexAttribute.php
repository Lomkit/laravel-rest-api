<?php

namespace Lomkit\Rest\Text\Engine;

use JsonSerializable;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator;

final readonly class IndexAttribute implements JsonSerializable
{
    private const array COMPARISONS = [
        FilterOperator::Equal,
        FilterOperator::NotEqual,
        FilterOperator::LessThan,
        FilterOperator::GreaterThan,
        FilterOperator::LessThanOrEqual,
        FilterOperator::GreaterThanOrEqual,
    ];

    /** @param list<FilterOperator> $operators */
    public function __construct(
        public Field $field,
        public array $operators,
        public EngineLiteral $literal = new PlainLiteral,
        public bool $list = false,
    ) {}

    public function sameAs(self $other): bool
    {
        return $this->field::class === $other->field::class
            && $this->list === $other->list
            && $this->jsonSerialize() === $other->jsonSerialize();
    }

    public function supports(FilterOperator $operator): bool
    {
        return in_array($operator, $this->operators, true);
    }

    public function comparison(FilterOperator|string $operator): FilterOperator
    {
        $comparison = $operator instanceof FilterOperator ? $operator : FilterOperator::tryFrom($operator);

        if ($comparison === null || ! in_array($comparison, self::COMPARISONS, true) || ! $this->supports($comparison)) {
            throw InvalidEngineQueryException::unsupportedOperator($this->field->name, $operator instanceof FilterOperator ? $operator->value : $operator);
        }

        return $comparison;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<int|float|string|bool>
     */
    public function members(FilterOperator $operator, array $values): array
    {
        if (! $this->supports($operator)) {
            throw InvalidEngineQueryException::unsupportedOperator($this->field->name, $operator->value);
        }

        return array_map(fn (mixed $value): int|float|string|bool => $this->value($value), array_values($values));
    }

    public function value(mixed $value): int|float|string|bool
    {
        $typed = EngineValue::of($this->field, $value) ?? throw InvalidEngineQueryException::invalidValue($this->field->name, $value);

        if (! LiteralValue::accepts($typed)) {
            throw InvalidEngineQueryException::nonLiteralValue($this->field->name);
        }

        return $this->literal->of($typed);
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
