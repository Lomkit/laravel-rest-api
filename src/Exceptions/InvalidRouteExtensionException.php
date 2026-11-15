<?php

namespace Lomkit\Rest\Exceptions;

use Lomkit\Rest\Http\Routing\Endpoint;

final class InvalidRouteExtensionException extends ExtensionException
{
    public static function invalidMiddleware(string $name, string $class, string $resource, Endpoint $endpoint, string $given): self
    {
        return self::about($name, "The [{$name}] extension {$class} returns {$given} in routeMiddleware() for the {$endpoint->value} routes of {$resource}. Return a list of middleware names as non-empty strings, such as 'throttle:quota' or QuotaMiddleware::class: route:cache stores each middleware as text.");
    }
}
