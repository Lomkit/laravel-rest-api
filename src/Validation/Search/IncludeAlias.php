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
            return 'The :attribute is not allowed on a nested relation, use a nested include instead.';
        }

        if (! is_string($value)) {
            return null;
        }

        return match (true) {
            $this->keys->isReserved($value) => 'The :attribute is reserved.',
            $this->keys->isField($value) => 'The :attribute conflicts with a field of this resource.',
            $this->keys->isRelation($value) => 'The :attribute conflicts with a relation of this resource.',
            $this->keys->includeUses($value) > 1 => 'The :attribute is already used by another include.',
            $this->keys->aggregateUses($value) > 0 => 'The :attribute is already used by an aggregate.',
            default => null,
        };
    }
}
