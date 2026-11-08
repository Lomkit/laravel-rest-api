<?php

namespace Lomkit\Rest\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ExistingKey implements ValidationRule
{
    /** @param array<string, int|string> $keys */
    public function __construct(
        private KeyLookup $lookup,
        private array $keys,
    ) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $refused = false;

        foreach ($this->keys as $path => $key) {
            if (! $this->lookup->has($key)) {
                $this->lookup->refuse($path, $key);
                $refused = true;
            }
        }

        if ($refused) {
            $fail('validation.exists')->translate();
        }
    }
}
