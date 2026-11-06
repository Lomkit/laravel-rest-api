<?php

namespace Lomkit\Rest;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Lomkit\Rest\Authorization\DecisionStamps;
use Lomkit\Rest\Authorization\UserIdentity;
use Lomkit\Rest\Config\ConfigValue;
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
        private readonly Factory $cache,
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

    public function forgetAuthorizationsFor(Authenticatable $user): void
    {
        DecisionStamps::renew($this->decisions(), UserIdentity::user($user));
    }

    public function forgetAuthorizations(): void
    {
        DecisionStamps::renew($this->decisions(), null);
    }

    /** @return list<string> */
    private function packageMiddleware(): array
    {
        if (! ConfigValue::of($this->config, 'rest.precognition.enabled')->boolean()) {
            return [EnforceExpectsJson::class];
        }

        return [EnforceExpectsJson::class, HandlePrecognitiveRequests::class];
    }

    private function decisions(): Cache
    {
        return $this->cache->store(ConfigValue::of($this->config, 'rest.authorizations.cache.store')->nullableString());
    }
}
