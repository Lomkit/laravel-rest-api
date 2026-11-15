<?php

namespace Lomkit\Rest\Authorization;

use Lomkit\Rest\Exceptions\InvalidResourceException;

final readonly class CacheContext
{
    /** @param array<array-key, int|float|string|bool|null> $values */
    private function __construct(public array $values) {}

    /** @param array<mixed> $declared */
    public static function of(array $declared, string $resource): self
    {
        $values = [];

        foreach ($declared as $key => $value) {
            if (! is_int($value) && ! is_string($value) && ! is_bool($value) && $value !== null) {
                throw InvalidResourceException::invalidCacheContext($resource, $key);
            }

            $values[$key] = $value;
        }

        ksort($values, SORT_STRING);

        return new self($values);
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    public function serialized(): string
    {
        return serialize($this->values);
    }
}
