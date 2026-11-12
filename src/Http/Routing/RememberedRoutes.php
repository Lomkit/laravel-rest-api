<?php

namespace Lomkit\Rest\Http\Routing;

use Closure;
use Lomkit\Rest\Http\Resource;

final class RememberedRoutes
{
    /** @var array<class-string<Resource>, array<string, list<DeclaredRoute>>> */
    private static array $declared = [];

    /**
     * @param  class-string<Resource>  $resource
     * @param  Closure(): list<DeclaredRoute>  $read
     * @return list<DeclaredRoute>
     */
    public static function remember(string $resource, Endpoint $endpoint, Closure $read): array
    {
        return self::$declared[$resource][$endpoint->value] ??= $read();
    }

    public static function forget(): void
    {
        self::$declared = [];
    }
}
