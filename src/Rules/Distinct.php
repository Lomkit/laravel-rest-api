<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class Distinct implements ValidationRule
{
    /** @var array<array-key, int> */
    private array $repeated;

    /** @param array<mixed> $values */
    public function __construct(array $values)
    {
        $counts = array_count_values(array_filter($values, is_string(...)));

        $this->repeated = array_filter($counts, fn (int $count): bool => $count > 1);
    }

    public static function among(mixed $entries, string $key): self
    {
        return new self(is_array($entries) ? array_column(array_filter($entries, is_array(...)), $key) : []);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && isset($this->repeated[$value])) {
            $fail('validation.distinct')->translate();
        }
    }
}
