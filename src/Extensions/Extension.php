<?php

namespace Lomkit\Rest\Extensions;

use Lomkit\Rest\Http\Resource;

abstract class Extension
{
    abstract public static function name(): string;

    /** @param class-string<Resource> $resource */
    public function appliesTo(string $resource): bool
    {
        return true;
    }
}
