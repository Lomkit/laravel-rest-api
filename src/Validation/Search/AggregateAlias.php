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
            $this->keys->isReserved($key) => 'rest::validation.alias_reserved',
            $this->keys->isField($key) => 'rest::validation.alias_taken_by_field',
            $this->keys->isRelation($key) => 'rest::validation.alias_taken_by_relation',
            $this->keys->includeUses($key) > 0 => 'rest::validation.alias_taken_by_include',
            $this->keys->aggregateUses($key) > 1 => 'rest::validation.alias_taken_by_another_aggregate',
            default => null,
        };
    }
}
