<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;

final readonly class RouteMiddlewareContext
{
    /**
     * @internal
     *
     * @param  class-string<Resource>  $resource
     * @param  class-string<Controller>  $controller
     */
    public function __construct(
        public string $resource,
        public Endpoint $endpoint,
        public string $controller,
        public string $routeName,
    ) {}
}
