<?php

namespace Lomkit\Rest\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

class ArrayWith implements ValidationRule
{
    protected array $keys;

    public function __construct(array $keys)
    {
        $this->keys = $keys;
    }

    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (!is_array($value)) {
            $fail('The '.$attribute.' field must be an array.');
        }
        foreach (array_keys($value) as $key) {
            if (!in_array($key, $this->keys)) {
                $fail('The '.$key.' key is not valid for the '.$attribute.' field.');
            }
        }
    }
}