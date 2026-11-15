<?php

namespace Lomkit\Rest\Tests\Fixtures\Extensions;

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

    public static function reset(): void
    {
        self::$built = [];
        self::$asked = [];
        self::$named = [];
        self::$checked = [];
        RenamableExtension::$given = RenamableExtension::NAME;
    }
}
