<?php

namespace Lomkit\Rest;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Lomkit\Rest\Exceptions\InvalidResourceRouteException;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Http\Routing\ResourceRegistrar;
use Lomkit\Rest\Http\Routing\RouteMiddleware;
use Lomkit\Rest\Http\Routing\UriSegment;

#[Singleton]
class Rest
{
    public function __construct(
        private readonly ResourceRegistrar $registrar,
        private readonly Repository $config,
    ) {}

    /** @param class-string<Controller> $controller */
    public function resource(string $name, string $controller): PendingResourceRegistration
    {
        if (func_num_args() > 2) {
            throw InvalidResourceRouteException::optionsArgumentRemoved($name);
        }

        if (is_subclass_of($controller, Controller::class)) {
            $controller::resourceClass();
        }

        return new PendingResourceRegistration(
            $this->registrar,
            UriSegment::resourceName($name),
            $controller,
            new RouteMiddleware($this->packageMiddleware()),
        );
    }

    /** @return list<string> */
    private function packageMiddleware(): array
    {
        if (! (bool) $this->config->get('rest.precognition.enabled', false)) {
            return [EnforceExpectsJson::class];
        }

        return [EnforceExpectsJson::class, HandlePrecognitiveRequests::class];
    }
}
