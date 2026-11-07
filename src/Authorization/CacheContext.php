<?php

namespace Lomkit\Rest\Authorization;

use Lomkit\Rest\Exceptions\InvalidResourceException;

final readonly class CacheContext
{
    /** @param array<mixed> $context */
    public static function of(array $context, string $resource): string
    {
        foreach ($context as $key => $value) {
            if (! is_int($value) && ! is_string($value) && ! is_bool($value) && $value !== null) {
                throw InvalidResourceException::invalidCacheContext($resource, $key);
            }
        }

        ksort($context, SORT_STRING);

        return serialize($context);
    }
}
