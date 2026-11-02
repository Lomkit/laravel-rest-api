<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Lomkit\Rest\Mutations\MutationAwareRule;
use Lomkit\Rest\Mutations\MutationContext;

class RecordsMutation implements MutationAwareRule, ValidationRule
{
    /** @var list<array{string, int|string|null, int}> */
    public static array $seen = [];

    private ?MutationContext $mutation = null;

    #[\Override]
    public function setMutation(MutationContext $mutation): static
    {
        $this->mutation = $mutation;

        return $this;
    }

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->mutation === null) {
            return;
        }

        self::$seen[] = [$this->mutation->operation->value, $this->mutation->key, $this->mutation->depth];
    }
}
