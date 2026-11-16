<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Search\FilterGroup;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Search\FilterParser;
use Lomkit\Rest\Validation\ValidationContext;

/** @internal */
final readonly class EngineFilterParser
{
    public function __construct(private EngineGrammar $engine) {}

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

    private function filter(Cursor $entry, ValidationContext $context): EngineFilterCondition|FilterGroup|null
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
        $groups = $this->engine->groups;

        $rules->gate($entry, $field, $operator);
        $rules->add($field->path, [$groups ? 'required_without:'.$nested->path : 'required', 'string', $context->grammar->allowed($context->vocabulary->of($context->resource, $context->grammar, 'filters', fn (): array => array_keys($this->engine->filters)))]);
        $children = match (true) {
            ! $groups => $this->prohibited($nested, $context),
            $context->reader->filled($nested) => $this->nested($entry, $context),
            default => null,
        };
        $rules->addWhenPresent($type, [Rule::in($groups ? ['and', 'or'] : ['and'])]);
        $rules->add($operator->path, self::operatorRules($declared));

        $boolean = (new FilterParser)->boolean($type, $context);

        if ($children !== null) {
            return $this->group($children, $context->deeper(), $boolean);
        }

        $resolved = $declared === null ? null : self::operator($operator->value ?? FilterOperator::Equal->value, $declared);

        if ($name === null || $declared === null || $resolved === null) {
            $rules->partial();

            return null;
        }

        $this->values($value, $declared, $resolved, $context);

        if ($context->reader->filled($nested) || ($type->present && ! $groups && $type->value !== 'and')) {
            $rules->partial();

            return null;
        }

        return new EngineFilterCondition($name, $declared, $resolved, $value->value, $boolean);
    }

    /** @return list<Cursor>|null */
    private function nested(Cursor $entry, ValidationContext $context): ?array
    {
        $nested = $entry->at('nested');

        if ($context->filterDepth >= $context->limits->depths->filters) {
            return $this->prohibited($nested, $context);
        }

        $context->rules->addWhenPresent($nested, [
            'prohibits:'.$entry->at('field')->path.','.$entry->at('operator')->path.','.$entry->at('value')->path,
            'array',
        ]);

        return $context->reader->entries($nested, $context->limits->sizes->filters, true);
    }

    private function prohibited(Cursor $nested, ValidationContext $context): null
    {
        $context->rules->add($nested->path, ['prohibited']);

        return null;
    }

    private function values(Cursor $value, IndexAttribute $declared, FilterOperator $operator, ValidationContext $context): void
    {
        $rules = $declared->field->filterValueRules($operator, $value->path, $context->limits->lengths);
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
