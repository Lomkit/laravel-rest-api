<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\ResourceDefinition;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Query\Trashed;

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
        $this->checkSoftDeletes($endpoints);

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

        return [...array_map(fn (DeclaredRoute $declaration): RouteDefinition => $this->declarationRoute($endpoint, $route, $declaration), $declarations), $route];
    }

    private function declarationRoute(Endpoint $endpoint, RouteDefinition $catchAll, DeclaredRoute $declaration): RouteDefinition
    {
        $key = $declaration->key;

        return new RouteDefinition(
            $catchAll->methods,
            $endpoint->uri($this->name, $this->verbs, $key),
            $catchAll->name.'.'.$key,
            $catchAll->uses,
            $catchAll->middleware->with($declaration->middleware)->without($declaration->withoutMiddleware),
            [(string) $endpoint->parameter() => $key],
        );
    }

    /** @return list<DeclaredRoute> */
    private static function declarations(Endpoint $endpoint, ResourceDefinition $definition): array
    {
        return match ($endpoint) {
            Endpoint::Operate => $definition->declarations()->actions()->routes(),
            Endpoint::Metric => $definition->declarations()->metrics()->routes(),
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

    /** @param list<Endpoint> $endpoints */
    private function checkSoftDeletes(array $endpoints): void
    {
        $endpoint = array_find($endpoints, fn (Endpoint $endpoint): bool => in_array($endpoint, Endpoint::softDeletes(), true));

        if ($endpoint === null || ! is_subclass_of($this->controller, Controller::class)) {
            return;
        }

        try {
            $resource = $this->controller::resourceClass();
            $model = $resource::modelClass();
        } catch (InvalidControllerException|InvalidResourceException) {
            return;
        }

        if (Trashed::supportedBy($model)) {
            return;
        }

        throw InvalidResourceException::softDeletesRoute($resource, $model, $endpoint->value);
    }

    private function middlewareFor(Endpoint $endpoint): RouteMiddleware
    {
        return $this->middleware->merge($this->endpointMiddleware[$endpoint->value] ?? new RouteMiddleware);
    }
}
