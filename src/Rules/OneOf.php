<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class OneOf implements ValidationRule
{
    /** @var array<array-key, int> */
    private array $allowed;

    /** @param array<string> $values */
    public function __construct(array $values)
    {
        $this->allowed = array_flip($values);
    }

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_scalar($value) && isset($this->allowed[(string) $value])) {
            return;
        }

        $fail('validation.in')->translate();
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_map(strval(...), array_keys($this->allowed));
    }
}
