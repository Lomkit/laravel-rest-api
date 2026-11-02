<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\FieldPath;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Search\FilterCondition;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Search\FilterNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class FilterParser
{
    public const array KEYS = ['field' => true, 'operator' => true, 'value' => true, 'type' => true, 'nested' => true];

    /** @param list<Cursor> $entries */
    public function group(array $entries, ValidationContext $context, string $boolean = 'and'): FilterGroup
    {
        $filters = [];

        foreach ($entries as $entry) {
            $filter = $this->filter($entry, $context);

            if ($filter !== null) {
                $filters[] = $filter;
            }
        }

        return new FilterGroup($filters, $boolean);
    }

    private function filter(Cursor $entry, ValidationContext $context): ?FilterNode
    {
        if (! $context->reader->object($entry)) {
            return null;
        }

        if ($entry->at('relation')->present) {
            return (new RelationGroupParser($this))->parse($entry, $context);
        }

        $context->reader->known($entry, self::KEYS);

        $rules = $context->rules;
        $field = $entry->at('field');
        $nested = $entry->at('nested');
        $operator = $entry->at('operator');
        $value = $entry->at('value');
        $name = is_string($field->value) ? $field->value : null;
        $path = $name === null ? null : $this->path($name, $entry, $context);

        $rules->add($field->path, ['string', 'required_without:'.$nested->path, new OneOf($path === null ? [] : [$name])]);
        $children = $this->nested($entry, $context);
        $rules->add($operator->path, self::operatorRules($path));
        $rules->addWhenPresent($entry->at('type'), [Rule::in(['or', 'and'])]);

        $resolved = $path === null ? null : self::operator($operator->value ?? FilterOperator::Equal->value, $path);
        $valueRules = $path === null || $resolved === null ? [] : $path->field->filterValueRules($resolved, $value->path);
        $items = $this->values($value, $valueRules, $context);
        $boolean = $this->boolean($entry->at('type'), $context);
        $group = $children === null ? null : $this->group($children, $context->deeper(), $boolean);

        foreach ($items as $item) {
            $rules->add($item->path, $valueRules[$value->path.'.*'] ?? []);
        }

        if ($group !== null) {
            return $group;
        }

        if ($context->reader->filled($nested) || $name === null || $path === null || $resolved === null) {
            return $this->partial($context);
        }

        return new FilterCondition($name, $path->field, $resolved, $value->value, $boolean);
    }

    private function path(string $name, Cursor $entry, ValidationContext $context): ?FieldPath
    {
        foreach (FieldPath::relationsAlong($context->resource, $name) as $relation) {
            $context->rules->reach(Check::onClass($relation->resource(), 'viewAny', $entry->path));
        }

        return FieldPath::resolve($context->resource, $name);
    }

    /** @return list<Cursor>|null */
    private function nested(Cursor $entry, ValidationContext $context): ?array
    {
        $nested = $entry->at('nested');

        if ($context->filterDepth >= $context->limits->depths->filters) {
            $context->rules->add($nested->path, ['prohibited']);

            return $nested->value === [] ? [] : null;
        }

        $context->rules->addWhenPresent($nested, [
            'prohibits:'.$entry->at('field')->path.','.$entry->at('operator')->path.','.$entry->at('value')->path,
            'array',
        ]);

        return $context->reader->entries($nested, $context->limits->sizes->filters, true);
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @return list<Cursor>
     */
    private function values(Cursor $value, array $rules, ValidationContext $context): array
    {
        if (! isset($rules[$value->path])) {
            return [];
        }

        $context->rules->add($value->path, $rules[$value->path]);

        if (! isset($rules[$value->path.'.*']) || ! $context->reader->admit($value, $context->limits->sizes->values)) {
            return [];
        }

        return $context->reader->items($value);
    }

    public function boolean(Cursor $type, ValidationContext $context): string
    {
        if (! $type->present) {
            return 'and';
        }

        if ($type->value === 'and' || $type->value === 'or') {
            return $type->value;
        }

        $context->rules->partial();

        return 'and';
    }

    private function partial(ValidationContext $context): null
    {
        $context->rules->partial();

        return null;
    }

    /** @return list<mixed> */
    private static function operatorRules(?FieldPath $path): array
    {
        if ($path === null) {
            return ['string', Rule::enum(FilterOperator::class)];
        }

        $operators = $path->field->filterOperators();
        $required = in_array(FilterOperator::Equal, $operators, true) ? [] : ['required'];

        return [...$required, 'string', Rule::enum(FilterOperator::class)->only($operators)];
    }

    private static function operator(mixed $operator, FieldPath $path): ?FilterOperator
    {
        $parsed = is_string($operator) ? FilterOperator::tryFrom($operator) : null;

        return $parsed !== null && in_array($parsed, $path->field->filterOperators(), true) ? $parsed : null;
    }
}
