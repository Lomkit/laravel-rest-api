<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Lomkit\Rest\Extensions\Contexts\RouteMiddlewareContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;

class StampRoutes extends Extension implements AddsRouteMiddleware
{
    #[\Override]
    public static function name(): string
    {
        return 'stamp';
    }

    /** @var (Closure(RouteMiddlewareContext): list<string>)|null */
    public static ?Closure $middleware = null;

    /** @var list<class-string>|null */
    public static ?array $resources = null;

    /** @var list<RouteMiddlewareContext> */
    public static array $contexts = [];

    public static int $built = 0;

    /** @var list<class-string> */
    public static array $ran = [];

    public function __construct()
    {
        self::$built++;
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return self::$resources === null || in_array($resource, self::$resources, true);
    }

    #[\Override]
    public function routeMiddleware(RouteMiddlewareContext $context): array
    {
        self::$contexts[] = $context;
        self::$ran[] = static::class;

        return self::$middleware === null ? [StampsRequests::class.':a'] : (self::$middleware)($context);
    }

    public static function reset(): void
    {
        self::$middleware = null;
        self::$resources = null;
        self::$contexts = [];
        self::$built = 0;
        self::$ran = [];
    }
}
