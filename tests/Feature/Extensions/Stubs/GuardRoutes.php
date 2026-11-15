<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\RouteMiddlewareContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

final class GuardRoutes extends Extension implements AddsRouteMiddleware
{
    /** @var list<string> */
    public static array $middleware = ['auth'];

    /** @var list<class-string> */
    public static array $resources = [StageResource::class];

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return in_array($resource, self::$resources, true);
    }

    #[\Override]
    public function routeMiddleware(RouteMiddlewareContext $context): array
    {
        return self::$middleware;
    }
}
