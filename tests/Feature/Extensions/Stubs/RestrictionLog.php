<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;

final class RestrictionLog
{
    /** @var list<string> */
    public static array $seen = [];

    /** @var list<?Authenticatable> */
    public static array $users = [];

    public static function reset(): void
    {
        self::$seen = [];
        self::$users = [];
    }
}
