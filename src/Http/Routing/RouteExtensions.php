<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Config\RestConfig;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionFactory;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;

/** @internal */
#[Singleton]
final class RouteExtensions
{
    /** @var array{mixed, mixed, list<string>}|null */
    private ?array $source = null;

    private RouteEntries $entries;

    /** @var array<string, Extension&AddsRouteMiddleware>|null */
    private ?array $built = null;

    /** @var array<class-string<Resource>, array<string, Extension&AddsRouteMiddleware>> */
    private array $applicable = [];

    public function __construct(
        private readonly Repository $config,
        private readonly Container $container,
        private readonly RuntimeExtensions $runtime,
    ) {}

    /** @param class-string $controller */
    public function middleware(string $controller): ExtensionMiddleware
    {
        if (! is_subclass_of($controller, Controller::class) || $this->classes() === []) {
            return ExtensionMiddleware::none();
        }

        $resource = $controller::resourceClass();

        return ExtensionMiddleware::of($this->applicable[$resource] ??= $this->applicableTo($resource), $this->entries->classes, $resource, $controller);
    }

    /** @return array<string, string> */
    private function classes(): array
    {
        $source = [$this->config->get(RestConfig::EXTENSIONS), $this->config->get(RestConfig::DISABLED_EXTENSIONS), $this->runtime->all()];

        if ($source !== $this->source) {
            $this->entries = RouteEntries::of($this->config, $this->container, $source[2]);
            $this->source = $source;
            $this->built = null;
            $this->applicable = [];
        }

        return $this->entries->classes;
    }

    /** @return array<string, Extension&AddsRouteMiddleware> */
    private function built(): array
    {
        if ($this->built !== null) {
            return $this->built;
        }

        $factory = new ExtensionFactory($this->container);
        $built = [];

        foreach ($this->entries->classes as $name => $class) {
            $extension = $this->entries->bound[$class] ?? $factory->make($class, $name);

            if ($extension instanceof AddsRouteMiddleware) {
                $built[$name] = $extension;
            }
        }

        return $this->built = $built;
    }

    /**
     * @param  class-string<Resource>  $resource
     * @return array<string, Extension&AddsRouteMiddleware>
     */
    private function applicableTo(string $resource): array
    {
        return array_filter($this->built(), static fn (Extension $extension): bool => $extension->appliesTo($resource));
    }
}
