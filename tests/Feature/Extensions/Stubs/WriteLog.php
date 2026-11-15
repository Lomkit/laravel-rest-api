<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Contexts\WriteObservationContext;

final class WriteLog
{
    /** @var list<WriteGuardContext> */
    public static array $guards = [];

    /** @var list<WriteObservationContext> */
    public static array $writes = [];

    /** @var (Closure(WriteObservationContext): void)|null */
    public static ?Closure $observing = null;

    public static ?int $refuseAt = null;

    public static string $refusal = 'The monthly quota is reached.';

    public static int $status = 429;

    public static function reset(): void
    {
        self::$guards = [];
        self::$writes = [];
        self::$observing = null;
        self::$refuseAt = null;
        self::$refusal = 'The monthly quota is reached.';
        self::$status = 429;
    }
}
