<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\PreparesRowChecks;

class SecondPreparingExtension extends Extension implements PreparesRowChecks
{
    #[\Override]
    public static function name(): string
    {
        return 'a';
    }

    /** @var list<PendingChecks> */
    public static array $received = [];

    /** @var (Closure(PendingChecks): void)|null */
    public static ?Closure $prepare = null;

    #[\Override]
    public function prepareRowChecks(PendingChecks $checks): void
    {
        self::$received[] = $checks;
        RowCheckLog::record('second', $checks);

        if (self::$prepare !== null) {
            (self::$prepare)($checks);
        }
    }
}
