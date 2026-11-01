<?php

namespace Lomkit\Rest\Rules;

use Lomkit\Rest\Validation\RuleCompiler;

final class MaxItems extends RestRule
{
    public function __construct(
        private readonly int $max,
        private readonly bool $filters = false,
    ) {}

    public static function filters(int $max): self
    {
        return new self($max, true);
    }

    #[\Override]
    public function compile(RuleCompiler $into, string $attribute, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $into->budget()->admit($attribute, count($value), $this->max, $this->filters);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        return [];
    }
}
