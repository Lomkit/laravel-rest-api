<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\ResourceDefinition;
use Lomkit\Rest\Http\RestContext;

final class EndpointDeclarations
{
    private ?ResourceDefinition $definition = null;

    /** @param class-string<Controller> $controller */
    public function __construct(
        private readonly string $controller,
        private readonly Container $container,
        private readonly bool $remember,
    ) {}

    /** @return list<DeclaredRoute> */
    public function for(Endpoint $endpoint): array
    {
        if (! in_array($endpoint, [Endpoint::Operate, Endpoint::Metric], true) || ! is_subclass_of($this->controller, Controller::class)) {
            return [];
        }

        if (! $this->remember) {
            return $this->read($endpoint);
        }

        return RememberedRoutes::remember($this->controller::resourceClass(), $endpoint, fn (): array => $this->read($endpoint));
    }

    /** @return list<DeclaredRoute> */
    private function read(Endpoint $endpoint): array
    {
        $declarations = $this->definition()->declarations();

        if ($endpoint === Endpoint::Operate) {
            return $declarations->actions()->routes();
        }

        return $declarations->metrics()->routes();
    }

    private function definition(): ResourceDefinition
    {
        if ($this->definition !== null) {
            return $this->definition;
        }

        $context = new RestContext(RestRequest::createFrom($this->container->make(Request::class)), $this->container);

        return $this->definition = $context
            ->resource($this->controller::resourceClass())
            ->definition();
    }
}
