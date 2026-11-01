<?php

namespace Lomkit\Rest\Rules\Search;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Lomkit\Rest\Text\Engine\EngineValue;
use Lomkit\Rest\Text\Engine\IndexAttribute;

final readonly class EngineComparable implements ValidationRule
{
    public function __construct(private IndexAttribute $attribute) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (EngineValue::of($this->attribute->field, $value) === null) {
            $fail('The :attribute field holds a value the search engine cannot compare.');
        }
    }
}
