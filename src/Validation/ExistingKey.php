<?php

namespace Lomkit\Rest\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ExistingKey implements ValidationRule
{
    /** @param list<int|string> $keys */
    public function __construct(
        private KeyLookup $lookup,
        private array $keys,
    ) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach ($this->keys as $key) {
            if (! $this->lookup->has($key)) {
                $fail('validation.exists')->translate();

                return;
            }
        }
    }
}
