<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Text\Engine\IndexAttribute;

final class EngineFilter extends RestRule
{
    public function __construct(private readonly EngineGrammar $grammar) {}

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $filter = is_array($value) ? $value : [];
        $field = $filter['field'] ?? null;
        $declared = is_string($field) ? ($this->grammar->filters[$field] ?? null) : null;

        return [
            $attribute.'.field' => ['required', 'string', new OneOf(array_keys($this->grammar->filters))],
            $attribute.'.nested' => ['prohibited'],
            $attribute.'.type' => ['sometimes', Rule::in(['and'])],
            $attribute.'.operator' => self::operatorRules($declared),
            ...self::valueRules($attribute.'.value', $declared, $filter['operator'] ?? FilterOperator::Equal->value),
        ];
    }

    /** @return list<mixed> */
    private static function operatorRules(?IndexAttribute $declared): array
    {
        if ($declared === null) {
            return ['string', Rule::enum(FilterOperator::class)];
        }

        $required = $declared->supports(FilterOperator::Equal) ? [] : ['required'];

        return [...$required, 'string', Rule::enum(FilterOperator::class)->only($declared->operators)];
    }

    /** @return array<string, list<mixed>> */
    private static function valueRules(string $attribute, ?IndexAttribute $declared, mixed $operator): array
    {
        $operator = is_string($operator) ? FilterOperator::tryFrom($operator) : null;

        if ($declared === null || $operator === null || ! $declared->supports($operator)) {
            return [];
        }

        $rules = $declared->field->filterValueRules($operator, $attribute);
        $compared = isset($rules[$attribute.'.*']) ? $attribute.'.*' : $attribute;
        $rules[$compared] = ['bail', ...$rules[$compared] ?? [], new EngineComparable($declared)];

        return $rules;
    }
}
