<?php

namespace Lomkit\Rest\Text\Engine;

use Closure;
use Lomkit\Rest\Rules\AllowedValues;

/** @internal */
final readonly class ListRefusal implements AllowedValues
{
    public function __construct(private ?AllowedValues $allowed = null) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->accepts($value)) {
            return;
        }

        $fail('rest::validation.served_list')->translate();
    }

    #[\Override]
    public function accepts(mixed $value): bool
    {
        if ($this->allowed === null) {
            return $value === null || $value === [];
        }

        return $this->allowed->accepts($value);
    }
}
