<?php

namespace Lomkit\Rest\Validation\Search;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class AggregateAlias implements ValidationRule
{
    public function __construct(private RowKeys $keys) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $message = $this->conflict($value);

        if ($message !== null) {
            $fail($message)->translate();
        }
    }

    public function conflict(string $key): ?string
    {
        return match (true) {
            $this->keys->isReserved($key) => 'The :attribute is reserved.',
            $this->keys->isField($key) => 'The :attribute conflicts with a field of this resource.',
            $this->keys->isRelation($key) => 'The :attribute conflicts with a relation of this resource.',
            $this->keys->includeUses($key) > 0 => 'The :attribute is already used by an include.',
            $this->keys->aggregateUses($key) > 1 => 'The :attribute is already used by another aggregate.',
            default => null,
        };
    }
}
