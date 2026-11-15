<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\RouteMiddlewareContext;

interface AddsRouteMiddleware extends ExtensionKind
{
    /** @return list<string> */
    public function routeMiddleware(RouteMiddlewareContext $context): array;
}
