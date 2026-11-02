<?php

namespace Lomkit\Rest\Validation\Mutate;

use Illuminate\Validation\ConditionalRules;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Fields\WriteRules;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Mutations\Contextual;
use Lomkit\Rest\Mutations\MutationAwareRule;
use Lomkit\Rest\Mutations\MutationContext;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\KeyPath;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\PayloadReader;
use Lomkit\Rest\Validation\RuleReferences;
use Lomkit\Rest\Validation\Violation;

final readonly class EntryRules
{
    public function __construct(
        private ParsedRules $rules,
        private ResourceRules $resourceRules,
        private PayloadReader $reader,
    ) {}

    public function attributes(Cursor $entry, Resource $resource, WriteOperation $write, MutationContext $mutation): void
    {
        $attributes = $entry->at('attributes');

        if ($attributes->present && ! is_array($attributes->value)) {
            return;
        }

        $this->emit($attributes, $this->resourceRules->for($resource, $write), $mutation);
    }

    public function writable(Cursor $values, FieldSet $fields, WriteOperation $write): void
    {
        if (! is_array($values->value)) {
            return;
        }

        $writable = $fields->writableOn($write);

        foreach (array_keys($values->value) as $key) {
            if (! $writable->has((string) $key)) {
                $this->rules->reject(Violation::notWritable($values->path, (string) $key, $write));
            }
        }
    }

    public function pivot(Cursor $entry, PivotRelation $relation, ?WriteOperation $write, ?MutationContext $mutation): void
    {
        $pivot = $entry->at('pivot');

        $this->rules->add($pivot->path, [
            'array',
            ...MutationParser::whenOperation('prohibited_if', $entry, fn (MutationOperation $operation): bool => $operation->pivotWriteOperation() === null),
        ]);
        $this->reader->object($pivot);

        if ($write === null || $mutation === null || ! is_array($pivot->value ?? [])) {
            return;
        }

        $this->writable($pivot, $relation->getPivotFields(), $write);
        $this->emit($pivot, $this->resourceRules->forPivot($relation, $write), $mutation);
    }

    private function emit(Cursor $values, WriteRules $rules, MutationContext $mutation): void
    {
        $given = is_array($values->value) ? $values->value : [];
        $paths = [];

        foreach ($rules->names() as $name) {
            $paths[$name] = $values->path.'.'.$name;
        }

        foreach ($rules->all() as $key => $list) {
            $this->emitKey($values->path.'.'.$key, $key, RuleReferences::rewrite($this->resolve($list, $mutation, $given), $paths), $given);
        }

        foreach ($rules->fields() as $field) {
            $this->rules->book()->field($values->at($field->name), $field);
        }
    }

    /**
     * @param  list<mixed>  $rules
     * @param  array<array-key, mixed>  $given
     */
    private function emitKey(string $path, string $key, array $rules, array $given): void
    {
        if (str_contains($key, '*') || ! in_array('sometimes', $rules, true)) {
            $this->rules->add($path, $rules);

            return;
        }

        if (KeyPath::has($given, $key)) {
            $this->rules->add($path, array_values(array_filter($rules, fn (mixed $rule): bool => $rule !== 'sometimes')));
        }
    }

    /**
     * @param  list<mixed>  $rules
     * @param  array<array-key, mixed>  $values
     * @return list<mixed>
     */
    private function resolve(array $rules, MutationContext $mutation, array $values): array
    {
        $resolved = [];

        foreach ($rules as $rule) {
            array_push($resolved, ...$this->resolveRule($rule, $mutation, $values));
        }

        return $resolved;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<mixed>
     */
    private function resolveRule(mixed $rule, MutationContext $mutation, array $values): array
    {
        return match (true) {
            $rule instanceof Contextual => $this->resolve($rule->resolve($mutation), $mutation, $values),
            $rule instanceof ConditionalRules => $this->resolve(WriteRules::listOf($rule->passes($values) ? $rule->rules($values) : $rule->defaultRules($values)), $mutation, $values),
            $rule instanceof MutationAwareRule => [(clone $rule)->setMutation($mutation)],
            default => [$rule],
        };
    }
}
