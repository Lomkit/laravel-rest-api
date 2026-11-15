<?php

namespace Lomkit\Rest\Authorization;

use Lomkit\Rest\Exceptions\InvalidResourceException;

/** @internal */
final readonly class CacheContext
{
    /** @param array<array-key, int|float|string|bool|null> $values */
    private function __construct(public array $values) {}

    /** @param array<mixed> $declared */
    public static function of(array $declared, string $resource, ?CacheContributions $contributions = null): self
    {
        $values = [];

        foreach ($declared as $key => $value) {
            if (! is_int($value) && ! is_string($value) && ! is_bool($value) && $value !== null) {
                throw InvalidResourceException::invalidCacheContext($resource, $key);
            }

            $values[$key] = $value;
        }

        if ($contributions !== null) {
            $values = self::withItems($values, $contributions);
        }

        ksort($values, SORT_STRING);

        return new self($values);
    }

    /**
     * @param  array<array-key, int|float|string|bool|null>  $values
     * @return array<array-key, int|float|string|bool|null>
     */
    private static function withItems(array $values, CacheContributions $contributions): array
    {
        foreach ($contributions->items() as $key => $value) {
            if (array_key_exists($key, $values)) {
                throw $contributions->keyTaken($key);
            }

            $values[$key] = $value;
        }

        return $values;
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
