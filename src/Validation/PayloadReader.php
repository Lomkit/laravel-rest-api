<?php

namespace Lomkit\Rest\Validation;

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
    public function entries(Cursor $list, int $max, bool $filters = false, FilterCounts $counts = new FilterCounts): ?array
    {
        if (! $list->present) {
            return null;
        }

        $entries = $this->admitted($list, $max, $filters, $counts);

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

    public function close(Cursor $list): void
    {
        $this->budget->close($list->path);
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

        return array_map($list->item(...), array_keys($list->value));
    }

    /** @param list<string> $keys */
    public function refuse(Cursor $object, array $keys): void
    {
        if (! is_array($object->value)) {
            return;
        }

        foreach (array_keys($object->value) as $key) {
            if (in_array($key, $keys, true)) {
                $this->rules->reject(Violation::unknown($object->at($key)->path));
            }
        }
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

            $this->rules->reject(Violation::unknown($object->at($key)->path));
        }
    }

    /** @return list<Cursor>|null */
    private function admitted(Cursor $list, int $max, bool $filters, FilterCounts $counts): ?array
    {
        if (is_array($list->value) && array_is_list($list->value)) {
            return $this->budget->admit($list->path, count($list->value), $max, $filters, $counts) ? $list->items(PHP_INT_MAX) : null;
        }

        if (is_array($list->value)) {
            $list->items(PHP_INT_MAX);
            $this->rules->add($list->path, []);
            $this->budget->close($list->path);
        }

        return null;
    }
}
