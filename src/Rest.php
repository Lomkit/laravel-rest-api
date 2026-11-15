<?php

namespace Lomkit\Rest;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Lomkit\Rest\Authorization\DecisionStamps;
use Lomkit\Rest\Authorization\UserIdentity;
use Lomkit\Rest\Config\ConfigValue;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Middleware\EnforceExpectsJson;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\PendingResourceRegistration;
use Lomkit\Rest\Http\Routing\ResourceRegistrar;
use Lomkit\Rest\Http\Routing\RouteMiddleware;
use Lomkit\Rest\Http\Routing\UriSegment;
use Lomkit\Rest\Search\Run\PendingSearch;

#[Singleton]
class Rest
{
    public function __construct(
        private readonly ResourceRegistrar $registrar,
        private readonly Repository $config,
        private readonly Container $container,
    ) {}

    /** @param class-string<Controller> $controller */
    public function resource(string $name, string $controller): PendingResourceRegistration
    {
        if (is_subclass_of($controller, Controller::class)) {
            $controller::resourceClass();
        }

        return new PendingResourceRegistration(
            $this->registrar,
            UriSegment::resourceName($name),
            $controller,
            new RouteMiddleware($this->packageMiddleware()),
            $this->container,
        );
    }

    /** @param class-string<Resource> $resource */
    public function search(string $resource): PendingSearch
    {
        return new PendingSearch($this->container, $resource);
    }

    public function forgetAuthorizationsFor(Authenticatable $user): void
    {
        DecisionStamps::renew($this->decisions(), UserIdentity::user($user));
    }

    public function forgetAuthorizations(): void
    {
        DecisionStamps::renew($this->decisions(), null);
    }

    /** @param class-string<Extension> $class */
    public function registerExtension(string $class): void
    {
        $this->container->make(RuntimeExtensions::class)->register($class);
    }

    /** @param list<class-string<Extension>> $classes */
    public function registerExtensions(array $classes): void
    {
        $this->container->make(RuntimeExtensions::class)->registerAll($classes);
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
        return $this->container->make(Factory::class)->store(ConfigValue::of($this->config, 'rest.authorizations.cache.store')->nullableString());
    }
}
