<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Lomkit\Rest\Exceptions\InvalidResourceRouteException;

final class ResourceRegistrar
{
    public function __construct(private readonly Router $router) {}

    /** @param list<RouteDefinition> $definitions */
    public function register(array $definitions): RouteCollection
    {
        $this->ensureDistinct($definitions);

        $routes = new RouteCollection;

        foreach ($definitions as $definition) {
            $route = $this->router->addRoute($definition->methods, $definition->uri, $definition->action());

            foreach ($definition->defaults as $key => $value) {
                $route->defaults($key, $value);
            }

            $routes->add($route);
        }

        return $routes;
    }

    /** @param list<RouteDefinition> $definitions */
    private function ensureDistinct(array $definitions): void
    {
        $uris = [];
        $names = [];

        foreach ($definitions as $definition) {
            foreach ($definition->methods as $method) {
                $route = $method.' '.$definition->uri;

                if (isset($uris[$route])) {
                    throw InvalidResourceRouteException::duplicateUri($route);
                }

                $uris[$route] = true;
            }

            if (isset($names[$definition->name])) {
                throw InvalidResourceRouteException::duplicateName($definition->name);
            }

            $names[$definition->name] = true;
        }
    }
}
