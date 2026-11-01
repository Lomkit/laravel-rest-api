<?php

namespace Lomkit\Rest\Validation;

use InvalidArgumentException;

final readonly class LimitGroup
{
    /** @param array<array-key, mixed> $values */
    private function __construct(
        private string $path,
        private array $values,
    ) {}

    public static function of(mixed $limits, string $name): self
    {
        $values = is_array($limits) ? ($limits[$name] ?? null) : null;

        return new self("rest.limits.{$name}", is_array($values) ? $values : []);
    }

    public function read(string $key): int
    {
        $value = $this->values[$key] ?? null;

        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException("The [{$this->path}.{$key}] option must be an integer of 0 or more.");
    }
}
