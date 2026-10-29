<?php

namespace Lomkit\Rest\Concerns;

trait Makeable
{
    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }
}
