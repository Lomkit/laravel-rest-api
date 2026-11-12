<?php

namespace Lomkit\Rest\Concerns;

use ReflectionClass;

trait Makeable
{
    public static function make(mixed ...$arguments): static
    {
        return (new ReflectionClass(static::class))->newInstanceArgs($arguments);
    }
}
