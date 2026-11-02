<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Search\FilterCondition;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Search\FilterParser;
use Lomkit\Rest\Validation\ValidationContext;

/** @internal */
final readonly class EngineFilterParser
{
    public function __construct(private EngineGrammar $engine) {}

    /** @param list<Cursor> $entries */
    public function group(array $entries, ValidationContext $context): FilterGroup
    {
        $filters = [];

        foreach ($entries as $entry) {
            $filter = $this->filter($entry, $context);

            if ($filter !== null) {
                $filters[] = $filter;
            }
        }

        return new FilterGroup($filters);
    }

    private function filter(Cursor $entry, ValidationContext $context): ?FilterCondition
    {
        if (! $context->reader->object($entry)) {
            return null;
        }

        $context->reader->known($entry, FilterParser::KEYS);

        $rules = $context->rules;
        $field = $entry->at('field');
        $nested = $entry->at('nested');
        $type = $entry->at('type');
        $operator = $entry->at('operator');
        $value = $entry->at('value');
        $name = is_string($field->value) ? $field->value : null;
        $declared = $name === null ? null : ($this->engine->filters[$name] ?? null);

        $rules->add($field->path, ['required', 'string', $context->vocabulary->of($context->resource, $context->grammar, 'filters', fn (): array => array_keys($this->engine->filters))]);
        $rules->add($nested->path, ['prohibited']);
        $rules->addWhenPresent($type, [Rule::in(['and'])]);
        $rules->add($operator->path, self::operatorRules($declared));

        $resolved = $declared === null ? null : self::operator($operator->value ?? FilterOperator::Equal->value, $declared);

        if ($name === null || $declared === null || $resolved === null) {
            $rules->partial();

            return null;
        }

        $this->values($value, $declared, $resolved, $context);

        if ($context->reader->filled($nested) || ($type->present && $type->value !== 'and')) {
            $rules->partial();

            return null;
        }

        return new FilterCondition($name, $declared->field, $resolved, $value->value);
    }

    private function values(Cursor $value, IndexAttribute $declared, FilterOperator $operator, ValidationContext $context): void
    {
        $rules = $declared->field->filterValueRules($operator, $value->path);
        $items = $value->path.'.*';
        $compared = isset($rules[$items]) ? $items : $value->path;
        $rules[$compared] = ['bail', ...$rules[$compared] ?? [], new EngineComparable($declared)];

        $context->rules->add($value->path, $rules[$value->path] ?? []);

        if ($compared === $value->path || ! $context->reader->admit($value, $context->limits->sizes->values)) {
            return;
        }

        foreach ($context->reader->items($value) as $item) {
            $context->rules->add($item->path, $rules[$items]);
        }
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

    private static function operator(mixed $operator, IndexAttribute $declared): ?FilterOperator
    {
        $parsed = is_string($operator) ? FilterOperator::tryFrom($operator) : null;

        return $parsed !== null && $declared->supports($parsed) ? $parsed : null;
    }
}
