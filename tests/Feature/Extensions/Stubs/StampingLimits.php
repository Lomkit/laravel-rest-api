<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\RouteMiddlewareContext;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;

final class StampingLimits extends PlainLimits implements AddsRouteMiddleware
{
    #[\Override]
    public function routeMiddleware(RouteMiddlewareContext $context): array
    {
        return [StampsRequests::class.':bound'];
    }
}
