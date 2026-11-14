<?php

namespace Lomkit\Rest\Extensions;

use Lomkit\Rest\Http\Resource;

/** @internal */
final class Applicability
{
    /** @var array<class-string<Extension>, array<class-string<Resource>, bool>> */
    private static array $answers = [];

    /** @param class-string<Resource> $resource */
    public static function of(Extension $extension, string $resource): bool
    {
        return self::$answers[$extension::class][$resource] ??= $extension->appliesTo($resource);
    }

    public static function flush(): void
    {
        self::$answers = [];
    }
}
