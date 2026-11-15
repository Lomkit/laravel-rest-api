<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Http\Resource;

final class ExtensionLog
{
    /** @var list<string> */
    public static array $built = [];

    /** @var list<string> */
    public static array $asked = [];

    /** @var list<string> */
    public static array $named = [];

    /** @var list<string> */
    public static array $checked = [];

    /** @var list<array{Extension, Resource}> */
    public static array $resources = [];

    public static function reset(): void
    {
        self::$built = [];
        self::$asked = [];
        self::$named = [];
        self::$checked = [];
        self::$resources = [];
        RenamableExtension::$given = RenamableExtension::NAME;
    }
}
