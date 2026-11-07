<?php

namespace Lomkit\Rest\Fields;

abstract readonly class FieldValues
{
    protected function __construct(protected CastValues $values) {}

    public function __get(string $name): mixed
    {
        return $this->values->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->values->filled($name);
    }

    public function has(string $name): bool
    {
        return $this->values->has($name);
    }

    public function filled(string $name): bool
    {
        return $this->values->filled($name);
    }

    /** @return array<string, mixed> */
    public function only(string ...$names): array
    {
        return $this->values->only(...$names);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values->toArray();
    }
}
