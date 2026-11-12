<?php

namespace Lomkit\Rest\Http\Routing;

use Closure;
use Lomkit\Rest\Http\Resource;

interface DeclaredRouteSource
{
    /**
     * @param  class-string<Resource>  $resource
     * @param  Closure(): list<DeclaredRoute>  $read
     * @return list<DeclaredRoute>
     */
    public function routes(string $resource, Endpoint $endpoint, Closure $read): array;
}
