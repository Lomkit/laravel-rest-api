<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class MaxLength implements ValidationRule
{
    public function __construct(private int $max) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->accepts($value)) {
            return;
        }

        $fail('validation.max.string')->translate(['max' => (string) $this->max]);
    }

    private function accepts(mixed $value): bool
    {
        return ! is_string($value) || mb_strlen($value) <= $this->max;
    }
}
