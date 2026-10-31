<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Rules\Concerns\DependsOnTextSearch;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Validation\RuleCompiler;

class SearchFilter extends RestRule
{
    use DependsOnTextSearch;

    protected int $depth = 0;

    public function setDepth(int $depth): static
    {
        $this->depth = $depth;

        return $this;
    }

    #[\Override]
    public function compile(RuleCompiler $into, string $attribute, mixed $value): void
    {
        $field = self::field($value);
        $relations = $this->textSearch || $field === null ? [] : FieldPath::relationsAlong($this->resource, $field);

        foreach ($relations as $relation) {
            $into->reach(Check::onClass($relation->resource(), 'viewAny', $attribute));
        }

        parent::compile($into, $attribute, $value);
    }

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        if ($this->textSearch) {
            return $this->textSearchRules($attribute);
        }

        $field = self::field($value);
        $path = $field === null ? null : FieldPath::resolve($this->resource, $field);
        $operator = is_array($value) ? ($value['operator'] ?? FilterOperator::Equal->value) : null;

        return [
            $attribute.'.field' => ['string', 'required_without:'.$attribute.'.nested', new OneOf($path === null ? [] : [$field])],
            $attribute.'.nested' => $this->depth < $this->resource->context()->maxNestingDepth()
                ? ['sometimes', 'prohibits:'.$attribute.'.field,'.$attribute.'.operator,'.$attribute.'.value', 'array']
                : ['prohibited'],
            $attribute.'.nested.*' => [(new SearchFilter)
                ->setResource($this->resource)
                ->setDepth($this->depth + 1)],
            $attribute.'.operator' => $this->operatorRules($path),
            $attribute.'.type' => ['sometimes', Rule::in(['or', 'and'])],
            ...$this->valueRules($attribute.'.value', $path, $operator),
        ];
    }

    private static function field(mixed $value): ?string
    {
        $field = is_array($value) ? ($value['field'] ?? null) : null;

        return is_string($field) ? $field : null;
    }

    /** @return list<mixed> */
    private function operatorRules(?FieldPath $path): array
    {
        if ($path === null) {
            return ['string', Rule::enum(FilterOperator::class)];
        }

        $operators = $path->field->filterOperators();
        $required = in_array(FilterOperator::Equal, $operators, true) ? [] : ['required'];

        return [...$required, 'string', Rule::enum(FilterOperator::class)->only($operators)];
    }

    /** @return array<string, list<mixed>> */
    private function valueRules(string $attribute, ?FieldPath $path, mixed $operator): array
    {
        $operator = is_string($operator) ? FilterOperator::tryFrom($operator) : null;

        if ($path === null || $operator === null || ! in_array($operator, $path->field->filterOperators(), true)) {
            return [];
        }

        return $path->field->filterValueRules($operator, $attribute);
    }

    /** @return array<string, list<mixed>> */
    private function textSearchRules(string $attribute): array
    {
        return [
            $attribute.'.field' => [
                'string',
                'required_without:'.$attribute.'.nested',
                new OneOf($this->resource->getScoutFields($this->resource->context()->request)),
            ],
            $attribute.'.nested' => ['prohibited'],
            $attribute.'.nested.*' => [(new SearchFilter)
                ->setResource($this->resource)
                ->setDepth($this->depth + 1)
                ->forTextSearch()],
            $attribute.'.operator' => ['string', Rule::in(['=', 'in', 'not in'])],
            $attribute.'.type' => ['prohibited'],
            $attribute.'.value' => ['exclude_if:'.$attribute.'.value,null', 'required_without:'.$attribute.'.nested'],
        ];
    }
}
