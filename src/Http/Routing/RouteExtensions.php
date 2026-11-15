<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionFactory;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;

/** @internal */
#[Singleton]
final class RouteExtensions
{
    /** @var array{mixed, array<string, string>}|null */
    private ?array $source = null;

    /** @var array<string, string> */
    private array $candidates = [];

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

        return ExtensionMiddleware::of($this->applicable[$resource] ??= $this->applicableTo($resource), $resource, $controller);
    }

    /** @return array<string, string> */
    private function classes(): array
    {
        $source = [$this->config->get('rest.extensions'), $this->runtime->all()];

        if ($source !== $this->source) {
            $this->source = $source;
            $this->candidates = self::candidates($source[0], $source[1], $this->container);
            $this->built = null;
            $this->applicable = [];
        }

        return $this->candidates;
    }

    /**
     * @param  array<string, string>  $registered
     * @return array<string, string>
     */
    private static function candidates(mixed $configured, array $registered, Container $container): array
    {
        $candidates = [];

        foreach (ExtensionList::merge(is_array($configured) ? $configured : [], $registered)->entries as $entry) {
            if (! $entry->disabled && $entry->class !== null && (is_subclass_of($entry->class, AddsRouteMiddleware::class) || $container->bound($entry->class))) {
                $candidates[$entry->name] = $entry->class;
            }
        }

        ksort($candidates);

        return $candidates;
    }

    /** @return array<string, Extension&AddsRouteMiddleware> */
    private function built(): array
    {
        if ($this->built !== null) {
            return $this->built;
        }

        $factory = new ExtensionFactory($this->container);
        $built = [];

        foreach ($this->candidates as $name => $class) {
            $extension = $factory->make($name, $class);

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
