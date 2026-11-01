<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Stringable;

final readonly class OneOf implements Stringable, ValidationRule
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
        if ($this->accepts($value)) {
            return;
        }

        $fail('validation.in')->translate();
    }

    public function accepts(mixed $value): bool
    {
        return is_scalar($value) && isset($this->allowed[(string) $value]);
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_map(strval(...), array_keys($this->allowed));
    }

    #[\Override]
    public function __toString(): string
    {
        return 'in:'.implode(',', $this->values());
    }
}
