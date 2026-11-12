<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

final class DeclarationLog
{
    /** @var list<string> */
    public static array $built = [];

    /** @var list<string> */
    public static array $journal = [];

    public static function reset(): void
    {
        self::$built = [];
        self::$journal = [];
    }
}
