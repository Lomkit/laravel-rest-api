<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\AggregateFunction;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Search\AggregateKey;
use Lomkit\Rest\Search\AggregateNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;
use Lomkit\Rest\Validation\Violation;

final readonly class AggregateParser
{
    private const array KEYS = ['relation' => true, 'type' => true, 'field' => true, 'alias' => true, 'filters' => true];

    /**
     * @param  list<Cursor>  $entries
     * @return list<AggregateNode>
     */
    public function parse(array $entries, ValidationContext $context, RowKeys $keys): array
    {
        $aggregates = [];

        foreach ($entries as $entry) {
            $aggregate = $this->aggregate($entry, $keys, $context);

            if ($aggregate !== null) {
                $aggregates[] = $aggregate;
            }
        }

        return $aggregates;
    }

    private function aggregate(Cursor $entry, RowKeys $keys, ValidationContext $context): ?AggregateNode
    {
        if (! $context->reader->object($entry)) {
            return null;
        }

        $context->reader->known($entry, self::KEYS);

        $rules = $context->rules;
        $relation = $entry->at('relation');
        $type = $entry->at('type');
        $alias = $entry->at('alias');
        $filters = $entry->at('filters');
        $relations = is_string($relation->value) ? $context->reachRelation($relation->value, $entry->path) : null;
        $related = $relations === null ? null : $relations[array_key_last($relations)]->resource();
        $function = is_string($type->value) ? AggregateFunction::tryFrom($type->value) : null;

        $rules->add($alias->path, is_string($alias->value)
            ? ['string', 'max:255', 'regex:'.RowKeys::PATTERN, new AggregateAlias($keys)]
            : ['string']);
        $rules->add($relation->path, ['required', new KnownRelation($context->resource)]);
        $rules->add($type->path, ['required', Rule::enum(AggregateFunction::class)]);

        if ($relations !== null && count($relations) > 1) {
            $rules->reject(Violation::nestedAggregate($relation->path));
            $rules->partial();

            return null;
        }

        if ($related === null) {
            $rules->partial();

            return null;
        }

        $field = $this->field($entry, $function, $related, $context);
        $rules->addWhenPresent($filters, ['array']);
        $nested = $context->related($related);
        $group = $nested->grammar->filters($context->reader->entries($filters, $context->limits->sizes->filters, true) ?? [], $nested);

        if ($function === null || ($alias->present && ! is_string($alias->value))) {
            $rules->partial();

            return null;
        }

        $name = is_string($alias->value) ? $alias->value : null;
        $key = AggregateKey::of($relation->value, $function, $field, $name);

        if ($name === null && (new AggregateAlias($keys))->conflict($key->value) !== null) {
            $rules->reject(Violation::takenKey($alias->path, $key->value));
            $rules->partial();

            return null;
        }

        return new AggregateNode($relation->value, $related, $function, $field, $name, $key, $group);
    }

    private function field(Cursor $entry, ?AggregateFunction $function, Resource $related, ValidationContext $context): ?Field
    {
        $field = $entry->at('field');
        $type = $entry->at('type')->path;
        $rules = [
            'required_if:'.$type.','.implode(',', array_column(AggregateFunction::needingField(), 'value')),
            'prohibited_if:'.$type.','.implode(',', array_column(AggregateFunction::takingNoField(), 'value')),
        ];

        if ($function === null || ! $function->needsField()) {
            $context->rules->add($field->path, $rules);

            return $this->unused($field, $context);
        }

        $fields = $related->getFields($context->resource->context()->request);
        $accepted = new OneOf($function->acceptedFieldNames($fields));
        $context->rules->add($field->path, [...$rules, $accepted]);

        if (! is_string($field->value) || ! $accepted->accepts($field->value)) {
            $context->rules->partial();

            return null;
        }

        return $fields->get($field->value);
    }

    private function unused(Cursor $field, ValidationContext $context): null
    {
        if ($context->reader->filled($field)) {
            $context->rules->partial();
        }

        return null;
    }
}
