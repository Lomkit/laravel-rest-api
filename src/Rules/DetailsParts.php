<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Lomkit\Rest\Http\Details\DetailsSelection;

final readonly class DetailsParts implements ValidationRule
{
    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || DetailsSelection::unknown($value) === []) {
            return;
        }

        $fail('The :attribute field accepts these values, separated by commas: :values.')->translate(['values' => implode(', ', DetailsSelection::values())]);
    }
}
