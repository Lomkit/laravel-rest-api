<?php

namespace Lomkit\Rest\Rules\Concerns;

use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Fields\FieldSet;

trait ValidatesFields
{
    protected function validateFields(Validator $validator, string $attribute, mixed $submitted, FieldSet $fields): void
    {
        if (! is_array($submitted)) {
            return;
        }

        $rules = [];

        foreach ($fields as $field) {
            $rules[$field->name] = $field->getRules()->shared();
        }

        $fieldsValidator = ValidatorFactory::make(self::pivot($submitted), $rules);

        foreach ($fieldsValidator->errors()->messages() as $name => $messages) {
            foreach ($messages as $message) {
                $validator->errors()->add($attribute.'.'.$name, $message);
            }
        }
    }

    /**
     * @param  array<mixed>  $submitted
     * @return array<string, mixed>
     */
    private static function pivot(array $submitted): array
    {
        $values = [];

        foreach ($submitted as $entry) {
            if (is_array($entry) && is_string($entry['name'] ?? null) && array_key_exists('value', $entry)) {
                $values[$entry['name']] = $entry['value'];
            }
        }

        return $values;
    }
}
