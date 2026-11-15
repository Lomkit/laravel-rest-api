<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;

final class EngineLog
{
    /** @var list<string> */
    public static array $calls = [];

    /** @var list<?Authenticatable> */
    public static array $users = [];

    /** @var list<object> */
    public static array $contexts = [];

    public static function reset(): void
    {
        self::$calls = [];
        self::$users = [];
        self::$contexts = [];
    }
}
