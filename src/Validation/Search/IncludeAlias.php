<?php

namespace Lomkit\Rest\Validation\Search;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class IncludeAlias implements ValidationRule
{
    public function __construct(private RowKeys $keys, private string $relation) {}

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = $this->conflict($value);

        if ($message !== null) {
            $fail($message)->translate();
        }
    }

    private function conflict(mixed $value): ?string
    {
        if (str_contains($this->relation, '.')) {
            return 'rest::validation.alias_on_nested_relation';
        }

        if (! is_string($value)) {
            return null;
        }

        return match (true) {
            $this->keys->isReserved($value) => 'rest::validation.alias_reserved',
            $this->keys->isField($value) => 'rest::validation.alias_taken_by_field',
            $this->keys->isRelation($value) => 'rest::validation.alias_taken_by_relation',
            $this->keys->includeUses($value) > 1 => 'rest::validation.alias_taken_by_another_include',
            $this->keys->aggregateUses($value) > 0 => 'rest::validation.alias_taken_by_aggregate',
            default => null,
        };
    }
}
