<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\RouteMiddlewareContext;

final class AppStampRoutes extends StampRoutes
{
    #[\Override]
    public function routeMiddleware(RouteMiddlewareContext $context): array
    {
        parent::routeMiddleware($context);

        return [StampsRequests::class.':app'];
    }
}
