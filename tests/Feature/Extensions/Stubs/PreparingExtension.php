<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\PreparesRowChecks;
use Lomkit\Rest\Http\Resource;

final class PreparingExtension extends Extension implements PreparesRowChecks
{
    /** @var list<class-string<Resource>>|null */
    public static ?array $resources = null;

    /** @var list<PendingChecks> */
    public static array $received = [];

    /** @var (Closure(PendingChecks): void)|null */
    public static ?Closure $prepare = null;

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return self::$resources === null || in_array($resource, self::$resources, true);
    }

    #[\Override]
    public function prepareRowChecks(PendingChecks $checks): void
    {
        self::$received[] = $checks;
        RowCheckLog::record('first', $checks);

        if (self::$prepare !== null) {
            (self::$prepare)($checks);
        }
    }
}
