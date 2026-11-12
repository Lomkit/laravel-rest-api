<?php

namespace Lomkit\Rest\Support;

use ReflectionMethod;

final readonly class Overrides
{
    /**
     * @param  object|class-string  $subject
     * @param  class-string  $base
     */
    public static function method(object|string $subject, string $method, string $base): bool
    {
        return (new ReflectionMethod($subject, $method))->class !== $base;
    }
}
