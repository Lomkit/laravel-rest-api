<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ArrayWithKey implements ValidationRule
{
    /** @param array<array-key, string> $keys */
    public function __construct(protected array $keys) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The '.$attribute.' field must be an array.');

            return;
        }

        foreach (array_keys($value) as $key) {
            if (! in_array($key, $this->keys)) {
                $fail('The '.$key.' key is not valid for the '.$attribute.' field.');
            }
        }
    }
}
