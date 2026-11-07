<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\ResourceDefinition;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Metrics\Metric;

final readonly class EndpointRoutes
{
    /**
     * @param  class-string<Controller>  $controller
     * @param  array<string, RouteMiddleware>  $endpointMiddleware
     */
    public function __construct(
        private UriSegment $name,
        private string $controller,
        private ResourceVerbs $verbs,
        private RouteNames $names,
        private RouteMiddleware $middleware,
        private array $endpointMiddleware,
        private Container $container,
    ) {}

    /**
     * @param  list<Endpoint>  $endpoints
     * @return list<RouteDefinition>
     */
    public function definitions(array $endpoints): array
    {
        $definition = $this->definition($endpoints);
        $definitions = [];

        foreach ($endpoints as $endpoint) {
            array_push($definitions, ...$this->for($endpoint, $definition));
        }

        return $definitions;
    }

    /** @return list<RouteDefinition> */
    private function for(Endpoint $endpoint, ?ResourceDefinition $definition): array
    {
        $route = new RouteDefinition(
            $endpoint->methods(),
            $endpoint->uri($this->name, $this->verbs),
            $this->names->for($endpoint),
            $this->controller.'@'.$endpoint->value,
            $this->middlewareFor($endpoint),
        );

        $declarations = $definition === null ? [] : self::declarations($endpoint, $definition);

        return [...array_map(fn (Action|Metric $declaration): RouteDefinition => $this->declarationRoute($endpoint, $route, $declaration), $declarations), $route];
    }

    private function declarationRoute(Endpoint $endpoint, RouteDefinition $catchAll, Action|Metric $declaration): RouteDefinition
    {
        $key = $declaration->uriKey();

        return new RouteDefinition(
            $catchAll->methods,
            $endpoint->uri($this->name, $this->verbs, $key),
            $catchAll->name.'.'.$key,
            $catchAll->uses,
            $catchAll->middleware->with($declaration->middleware)->without($declaration->withoutMiddleware),
            [(string) $endpoint->parameter() => $key],
        );
    }

    /** @return list<Action|Metric> */
    private static function declarations(Endpoint $endpoint, ResourceDefinition $definition): array
    {
        return match ($endpoint) {
            Endpoint::Operate => array_values($definition->actions()),
            Endpoint::Metric => array_values($definition->metrics()),
            default => [],
        };
    }

    /** @param list<Endpoint> $endpoints */
    private function definition(array $endpoints): ?ResourceDefinition
    {
        if (! array_any($endpoints, fn (Endpoint $endpoint): bool => in_array($endpoint, [Endpoint::Operate, Endpoint::Metric], true)) || ! is_subclass_of($this->controller, Controller::class)) {
            return null;
        }

        $context = new RestContext(RestRequest::createFrom($this->container->make(Request::class)), $this->container);

        return $context
            ->resource($this->controller::resourceClass())
            ->definition();
    }

    private function middlewareFor(Endpoint $endpoint): RouteMiddleware
    {
        return $this->middleware->merge($this->endpointMiddleware[$endpoint->value] ?? new RouteMiddleware);
    }
}
