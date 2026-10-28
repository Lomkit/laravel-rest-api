<?php

namespace Lomkit\Rest\Concerns;

trait Makeable
{
    /**
     * Create a new element.
     *
     * @return static
     */
    public static function make(mixed ...$arguments)
    {
        return new static(...$arguments);
    }
}
