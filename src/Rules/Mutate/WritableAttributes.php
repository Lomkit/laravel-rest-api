<?php

namespace Lomkit\Rest\Rules\Mutate;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\WriteOperation;

final readonly class WritableAttributes implements ValidationRule
{
    public function __construct(private FieldSet $fields, private WriteOperation $operation) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $writable = $this->fields->writableOn($this->operation);

        foreach (array_keys($value) as $key) {
            if (! $writable->has((string) $key)) {
                $fail('The '.$key.' attribute is not writable on '.$this->operation->value.'.');
            }
        }
    }
}
