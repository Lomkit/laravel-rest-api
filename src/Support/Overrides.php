<?php

namespace Lomkit\Rest\Support;

use ReflectionMethod;

final readonly class Overrides
{
    /** @param class-string $base */
    public static function method(object $object, string $method, string $base): bool
    {
        return (new ReflectionMethod($object, $method))->class !== $base;
    }
}
