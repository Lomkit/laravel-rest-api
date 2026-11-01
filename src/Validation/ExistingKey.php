<?php

namespace Lomkit\Rest\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ExistingKey implements ValidationRule
{
    public function __construct(
        private KeyLookup $lookup,
        private int|string $key,
    ) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->lookup->has($this->key)) {
            $fail('validation.exists')->translate();
        }
    }
}
