<?php

namespace Lomkit\Rest\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Stringable;

final readonly class MaxDepth implements Stringable, ValidationRule
{
    public function __construct(private int $levels) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->accepts($value)) {
            return;
        }

        $fail('The :attribute field goes deeper than the limit of :max levels.')->translate(['max' => (string) $this->levels]);
    }

    public function accepts(mixed $value): bool
    {
        return ! is_array($value) || self::within($value, $this->levels);
    }

    #[\Override]
    public function __toString(): string
    {
        return 'max_depth:'.$this->levels;
    }

    /** @param array<mixed> $value */
    private static function within(array $value, int $levels): bool
    {
        if ($levels < 1) {
            return false;
        }

        foreach ($value as $item) {
            if (is_array($item) && ! self::within($item, $levels - 1)) {
                return false;
            }
        }

        return true;
    }
}
