<?php

namespace Lomkit\Rest\Validation\Search;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Lomkit\Rest\Validation\Cursor;

final readonly class InstructionAlias implements ValidationRule
{
    /** @param array<string, int> $uses */
    private function __construct(private array $uses) {}

    /** @param list<Cursor> $entries */
    public static function of(array $entries): self
    {
        $uses = [];

        foreach ($entries as $entry) {
            $key = self::key($entry);

            if ($key !== null) {
                $uses[$key] = ($uses[$key] ?? 0) + 1;
            }
        }

        return new self($uses);
    }

    public function isShared(string $key): bool
    {
        return ($this->uses[$key] ?? 0) > 1;
    }

    #[\Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $this->isShared($value)) {
            $fail('The :attribute is already used by another instruction.')->translate();
        }
    }

    private static function key(Cursor $entry): ?string
    {
        $alias = $entry->at('alias');

        if ($alias->present) {
            return is_string($alias->value) ? $alias->value : null;
        }

        $name = $entry->at('name')->value;

        return is_string($name) ? $name : null;
    }
}
