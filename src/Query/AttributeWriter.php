<?php

namespace Lomkit\Rest\Query;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\MutationOperation;

final readonly class AttributeWriter
{
    public function __construct(private FieldSet $fields) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     * @param  array<array-key, mixed>  $owned
     */
    public function write(Model $model, MutationOperation $operation, array $attributes, array $owned = []): Model
    {
        $write = $operation->writeOperation();
        $callbacks = [];

        foreach ($write === null ? [] : $this->fields->writableOn($write) as $field) {
            if (! array_key_exists($field->name, $attributes)) {
                continue;
            }

            if ($field->fillsThroughCallback()) {
                $callbacks[] = $field;

                continue;
            }

            $field->fill($model, $attributes[$field->name]);
        }

        foreach ($callbacks as $field) {
            $field->fill($model, $attributes[$field->name]);
        }

        return $model->forceFill($owned);
    }
}
