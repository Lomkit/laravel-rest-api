<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

final class ExtensionLog
{
    /** @var list<string> */
    public static array $built = [];

    /** @var list<string> */
    public static array $asked = [];

    public static function reset(): void
    {
        self::$built = [];
        self::$asked = [];
    }
}
