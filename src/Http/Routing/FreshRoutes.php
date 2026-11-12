<?php

namespace Lomkit\Rest\Http\Routing;

use Closure;

final readonly class FreshRoutes implements DeclaredRouteSource
{
    #[\Override]
    public function routes(string $resource, Endpoint $endpoint, Closure $read): array
    {
        return $read();
    }
}
