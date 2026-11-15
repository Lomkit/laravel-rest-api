<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\RouteMiddlewareContext;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;

final class RoutedStampNamedLimits extends StampNamedLimits implements AddsRouteMiddleware
{
    public static int $built = 0;

    public function __construct()
    {
        self::$built++;
    }

    #[\Override]
    public function routeMiddleware(RouteMiddlewareContext $context): array
    {
        return [StampsRequests::class.':bound'];
    }
}
