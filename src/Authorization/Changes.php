<?php

namespace Lomkit\Rest\Authorization;

final readonly class Changes
{
    /** @param array<array-key, mixed> $values */
    public function __construct(private array $values) {}

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(strval(...), array_keys($this->values));
    }

    /** @return array<array-key, mixed> */
    public function all(): array
    {
        return $this->values;
    }
}
