<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;

/** @internal */
final readonly class RouteExtensionClasses
{
    public static function present(mixed $entries, Container $container): bool
    {
        if (! is_array($entries)) {
            return false;
        }

        return array_any($entries, static fn (mixed $class): bool => is_string($class) && (is_subclass_of($class, AddsRouteMiddleware::class) || $container->bound($class)));
    }
}
