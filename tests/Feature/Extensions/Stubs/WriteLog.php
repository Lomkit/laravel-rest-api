<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Lomkit\Rest\Extensions\Contexts\ReadObservationContext;
use Lomkit\Rest\Extensions\Contexts\WriteGuardContext;
use Lomkit\Rest\Extensions\Contexts\WriteObservationContext;

final class WriteLog
{
    /** @var list<WriteGuardContext> */
    public static array $guards = [];

    /** @var list<WriteObservationContext> */
    public static array $writes = [];

    /** @var list<ReadObservationContext> */
    public static array $reads = [];

    /** @var (Closure(WriteObservationContext): void)|null */
    public static ?Closure $observing = null;

    public static ?int $refuseAt = null;

    public static string $refusal = 'The monthly quota is reached.';

    public static int $status = 429;

    /** @var list<int> */
    public static array $frozen = [];

    public static function reset(): void
    {
        self::$guards = [];
        self::$writes = [];
        self::$reads = [];
        self::$observing = null;
        self::$refuseAt = null;
        self::$refusal = 'The monthly quota is reached.';
        self::$status = 429;
        self::$frozen = [];
    }
}
