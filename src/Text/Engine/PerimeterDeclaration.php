<?php

namespace Lomkit\Rest\Text\Engine;

use Closure;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;

/** @internal */
final readonly class PerimeterDeclaration
{
    public static function ensure(?Closure $perimeter, bool $withoutPerimeter, string $resource): void
    {
        if ($perimeter === null && ! $withoutPerimeter) {
            throw InvalidTextSearchException::missingPerimeter($resource);
        }

        if ($perimeter !== null && $withoutPerimeter) {
            throw InvalidTextSearchException::conflictingPerimeter($resource);
        }
    }
}
