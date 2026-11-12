<?php

namespace Lomkit\Rest\Http\Routing;

use Closure;

final class ClassRoutes
{
    /** @var array<string, DeclaredRoute> */
    private static array $routes = [];

    /** @param Closure(): DeclaredRoute $read */
    public static function remember(DeclarationKind $kind, string $class, Closure $read): DeclaredRoute
    {
        return self::$routes["{$kind->name} {$class}"] ??= $read();
    }
}
