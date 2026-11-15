<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Lomkit\Rest\Extensions\Contexts\CacheContributionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\ContributesCacheContext;
use Lomkit\Rest\Http\Resource;

final class SecondCacheContext extends Extension implements ContributesCacheContext
{
    /** @var list<class-string<Resource>>|null */
    public static ?array $resources = null;

    /** @var (Closure(CacheContributionContext): void)|null */
    public static ?Closure $contribute = null;

    /** @var list<CacheContributionContext> */
    public static array $contexts = [];

    public static function reset(): void
    {
        self::$resources = null;
        self::$contribute = null;
        self::$contexts = [];
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return self::$resources === null || in_array($resource, self::$resources, true);
    }

    #[\Override]
    public function contributeCacheContext(CacheContributionContext $context): void
    {
        self::$contexts[] = $context;

        if (self::$contribute !== null) {
            (self::$contribute)($context);
        }
    }
}
