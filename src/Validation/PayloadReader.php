<?php

namespace Lomkit\Rest\Validation;

use Illuminate\Support\Str;

final readonly class PayloadReader
{
    public function __construct(
        private ParsedRules $rules,
        private NodeBudget $budget,
    ) {}

    public function object(Cursor $value): bool
    {
        if (is_array($value->value)) {
            return true;
        }

        if (! $value->present) {
            return false;
        }

        $this->rules->partial();

        if (! $this->rules->holds($value->path, 'array')) {
            $this->rules->add($value->path, ['array']);
        }

        return false;
    }

    /** @return list<Cursor>|null */
    public function entries(Cursor $list, int $max, bool $filters = false): ?array
    {
        if (! $list->present) {
            return null;
        }

        $entries = $this->admitted($list, $max, $filters);

        if ($entries === null) {
            $this->rules->partial();
        }

        return $entries;
    }

    public function admit(Cursor $list, int $max, bool $filters = false): bool
    {
        if (! is_array($list->value)) {
            return false;
        }

        if ($this->budget->admit($list->path, count($list->value), $max, $filters)) {
            return true;
        }

        $this->rules->partial();

        return false;
    }

    public function refused(Cursor $list): bool
    {
        return $this->budget->refused($list->path);
    }

    public function filled(Cursor $value): bool
    {
        return $value->value !== null && $value->value !== [];
    }

    /** @return list<Cursor> */
    public function items(Cursor $list): array
    {
        if (! is_array($list->value) || ! array_is_list($list->value)) {
            return [];
        }

        return array_map($list->at(...), array_keys($list->value));
    }

    /** @param array<string, true> $keys */
    public function known(Cursor $object, array $keys): void
    {
        if (! is_array($object->value)) {
            return;
        }

        foreach (array_keys($object->value) as $key) {
            if (isset($keys[$key])) {
                continue;
            }

            if (! KeyPath::canName($key)) {
                $this->rules->reject(Violation::unnamedKey($object->path, (string) $key));

                continue;
            }

            $path = $object->at($key)->path;

            $this->rules->reject(Violation::unknown($path));
            $this->labelAsWritten($path);
        }
    }

    /** @return list<Cursor>|null */
    private function admitted(Cursor $list, int $max, bool $filters): ?array
    {
        if (is_array($list->value) && array_is_list($list->value)) {
            return $this->budget->admit($list->path, count($list->value), $max, $filters) ? $list->items(PHP_INT_MAX) : null;
        }

        if (is_array($list->value)) {
            $list->items(PHP_INT_MAX);
            $this->rules->add($list->path, []);
            $this->budget->close($list->path);
        }

        return null;
    }

    private function labelAsWritten(string $key): void
    {
        $written = KeyPath::unescape($key);

        if (array_key_exists($written, $this->rules->labels()) || str_replace('_', ' ', Str::snake($written)) === $written) {
            return;
        }

        $this->rules->label($key, $written);
    }
}
