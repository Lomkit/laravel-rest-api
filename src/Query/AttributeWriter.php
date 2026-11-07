<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\MutationOperation;

final readonly class AttributeWriter
{
    public function __construct(private FieldSet $fields) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     * @param  array<string, mixed>  $owned
     */
    public function write(Model $model, MutationOperation $operation, array $attributes, array $owned = []): Model
    {
        $this->preview($model, $operation, $attributes);

        foreach ($this->written($operation, $attributes) as $field) {
            if ($field->fillsThroughCallback()) {
                $field->fill($model, $attributes[$field->name]);
            }
        }

        return $model->forceFill($owned);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @param  array<string, mixed>  $owned
     */
    public function preview(Model $model, MutationOperation $operation, array $attributes, array $owned = []): Model
    {
        foreach ($this->written($operation, $attributes) as $field) {
            if (! $field->fillsThroughCallback()) {
                $field->fill($model, $attributes[$field->name]);
            }
        }

        return $model->forceFill($owned);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return list<Field>
     */
    private function written(MutationOperation $operation, array $attributes): array
    {
        $write = $operation->writeOperation();
        $fields = [];

        foreach ($write === null ? [] : $this->fields->writableOn($write) as $field) {
            if (array_key_exists($field->name, $attributes)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
