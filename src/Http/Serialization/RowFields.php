<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Search\Plan\QueryPlan;

final readonly class RowFields
{
    /**
     * @param  list<string>  $columns
     * @param  list<Field>  $resolved
     * @param  array<string, string>  $aggregates
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
        $aggregates = [];

        foreach ($plan->aggregates as $aggregate) {
            $aggregates[$aggregate->label] = $aggregate->key->value;
        }

        return new self(
            $fields,
            array_map(static fn (Field $field): string => $field->name, array_values(array_diff_key($named, $resolved))),
            array_values($resolved),
            $aggregates,
        );
    }

    /** @return array<string, mixed> */
    public function values(Model $model): array
    {
        $attributes = $model->attributesToArray();
        $stored = $this->fields->fromStorage(array_diff_key($attributes, $this->aggregates));
        $row = [];

        foreach (array_keys($model->getAttributes() + $attributes) as $name) {
            $key = $this->aggregates[$name] ?? null;

            if ($key !== null && self::shows($model, $key)) {
                $row[$key] = $model->getAttributeValue($name);
            } elseif (array_key_exists($name, $stored) && in_array($name, $this->columns, true)) {
                $row[$name] = $stored[$name];
            }
        }

        foreach ($this->resolved as $field) {
            $row[$field->name] = $field->resolve($model);
        }

        return $row;
    }

    private static function shows(Model $model, string $key): bool
    {
        $visible = $model->getVisible();

        return ($visible === [] || in_array($key, $visible, true)) && ! in_array($key, $model->getHidden(), true);
    }
}
