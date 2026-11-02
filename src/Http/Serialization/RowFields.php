<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Query\Clauses\AggregateClause;
use Lomkit\Rest\Search\Plan\QueryPlan;

final readonly class RowFields
{
    /**
     * @param  list<string>  $columns
     * @param  list<Field>  $resolved
     * @param  list<string>  $aggregates
     */
    private function __construct(
        private FieldSet $fields,
        private array $columns,
        private array $resolved,
        private array $aggregates,
    ) {}

    public static function of(QueryPlan $plan): self
    {
        $fields = $plan->resource->getFields($plan->resource->context()->request);
        $names = array_values(array_unique($plan->selects ?? $fields->selectable()->names()));
        $named = array_map($fields->get(...), $names);
        $resolved = array_filter($named, static fn (Field $field): bool => $field->resolvesThroughCallback());

        return new self(
            $fields,
            array_map(static fn (Field $field): string => $field->name, array_values(array_diff_key($named, $resolved))),
            array_values($resolved),
            array_map(static fn (AggregateClause $aggregate): string => $aggregate->key->value, $plan->aggregates),
        );
    }

    /** @return array<string, mixed> */
    public function values(Model $model): array
    {
        $attributes = $model->attributesToArray();
        $columns = array_diff_key($attributes, array_flip($this->aggregates));
        $row = array_intersect_key(
            array_replace($attributes, $this->fields->fromStorage($columns)),
            array_flip([...$this->columns, ...$this->aggregates]),
        );

        foreach ($this->resolved as $field) {
            $row[$field->name] = $field->resolve($model);
        }

        return $row;
    }
}
