<?php

namespace Lomkit\Rest\Mutations;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LogicException;
use Lomkit\Rest\Fields\WriteRules;

final readonly class Contextual implements ValidationRule
{
    /** @param Closure(MutationContext): mixed $rules */
    private function __construct(private Closure $rules) {}

    /** @param Closure(MutationContext): mixed $rules */
    public static function rule(Closure $rules): self
    {
        return new self($rules);
    }

    /** @return list<mixed> */
    public function resolve(MutationContext $mutation): array
    {
        return WriteRules::listOf(($this->rules)($mutation));
    }

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        throw new LogicException('A contextual rule only applies to the attributes and the pivot of a mutation.');
    }
}
