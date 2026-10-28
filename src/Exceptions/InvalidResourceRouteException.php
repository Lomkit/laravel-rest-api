<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;
use Lomkit\Rest\Http\Routing\Endpoint;

final class InvalidResourceRouteException extends LogicException
{
    public static function nestedName(string $name): self
    {
        return new self("The resource name [{$name}] contains a dot. Register the child resource on its own and scope its query in the resource.");
    }

    public static function prefixedName(string $name): self
    {
        return new self("The resource name [{$name}] contains a slash. Wrap the call in Route::prefix() instead.");
    }

    public static function malformedName(string $name): self
    {
        return new self("The resource name [{$name}] must be one URI segment made of letters, digits, dashes and underscores.");
    }

    public static function optionsArgumentRemoved(string $name): self
    {
        return new self("Rest::resource() no longer takes an options array for [{$name}]. Use only(), except(), names(), middleware() and withSoftDeletes() instead.");
    }

    public static function unknownEndpoint(string $key): self
    {
        $endpoints = implode(', ', array_map(fn (Endpoint $endpoint): string => $endpoint->value, Endpoint::cases()));

        return new self("Unknown endpoint [{$key}]. The endpoints are: {$endpoints}.");
    }

    public static function notSoftDeletable(Endpoint $endpoint): self
    {
        $endpoints = implode(', ', array_map(fn (Endpoint $endpoint): string => $endpoint->value, Endpoint::optIns()));

        return new self("The endpoint [{$endpoint->value}] is not a soft delete endpoint. withSoftDeletes() accepts: {$endpoints}.");
    }

    public static function emptyName(): self
    {
        return new self('A route name cannot be empty.');
    }

    public static function invalidVerb(string $key): self
    {
        return new self("The resource verb [{$key}] must be one URI segment made of letters, digits, dashes and underscores.");
    }

    public static function duplicateUri(string $route): self
    {
        return new self("Two endpoints of the resource use the route [{$route}].");
    }

    public static function duplicateName(string $name): self
    {
        return new self("Two endpoints of the resource use the route name [{$name}].");
    }
}
