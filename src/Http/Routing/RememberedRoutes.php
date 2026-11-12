<?php

namespace Lomkit\Rest\Http\Routing;

use Closure;
use Lomkit\Rest\Http\Resource;

final class RememberedRoutes implements DeclaredRouteSource
{
    /** @var array<class-string<Resource>, array<string, list<DeclaredRoute>>> */
    private static array $declared = [];

    public static function forget(): void
    {
        self::$declared = [];
    }

    #[\Override]
    public function routes(string $resource, Endpoint $endpoint, Closure $read): array
    {
        return self::$declared[$resource][$endpoint->value] ??= $read();
    }
}
