<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;

final class WriteLog
{
    /** @var list<WriteGuardContext> */
    public static array $guards = [];

    public static ?int $refuseAt = null;

    public static string $refusal = 'The monthly quota is reached.';

    public static int $status = 429;

    public static function reset(): void
    {
        self::$guards = [];
        self::$refuseAt = null;
        self::$refusal = 'The monthly quota is reached.';
        self::$status = 429;
    }
}
