<?php

namespace Lomkit\Rest\Rules\Concerns;

use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Validator;

trait ValidatesFields
{
    /**
     * Pivot a positional [{name, value}] field list into an associative map
     * and validate it against the declared field rules, pushing any resulting
     * errors into the main validator under the given attribute prefix.
     *
     * @param Validator $validator       The main validator receiving the errors.
     * @param string    $attribute       The prefix for error keys (e.g. "fields").
     * @param mixed     $submittedFields The raw submitted fields list.
     * @param array     $declaredRules   The field rules keyed by field name.
     */
    protected function validateFields(
        Validator $validator,
        string $attribute,
        mixed $submittedFields,
        array $declaredRules
    ): void {
        if (!is_array($submittedFields)) {
            return;
        }

        $pivoted = collect($submittedFields)
            ->filter(function ($field) {
                return is_array($field) && array_key_exists('name', $field);
            })
            ->mapWithKeys(function ($field) {
                return [$field['name'] => $field['value'] ?? null];
            })
            ->toArray();

        $fieldsValidator = ValidatorFactory::make($pivoted, $declaredRules);

        foreach ($fieldsValidator->errors()->messages() as $name => $messages) {
            foreach ($messages as $message) {
                $validator->errors()->add($attribute.'.'.$name, $message);
            }
        }
    }
}
