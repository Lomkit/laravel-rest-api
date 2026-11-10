<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Database\Eloquent\Model;

final readonly class Changes
{
    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<string, Model>  $targets
     */
    public function __construct(
        private array $values,
        private array $targets = [],
    ) {}

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function target(string $key): ?Model
    {
        return $this->targets[$key] ?? null;
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
