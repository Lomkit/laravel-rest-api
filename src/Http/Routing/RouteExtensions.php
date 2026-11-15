<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Config\RestConfig;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionEntry;
use Lomkit\Rest\Extensions\ExtensionFactory;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;
use Lomkit\Rest\Extensions\NamedExtensions;
use Lomkit\Rest\Extensions\RuntimeExtensions;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Resource;

/** @internal */
#[Singleton]
final class RouteExtensions
{
    /** @var array{mixed, mixed, list<string>}|null */
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
        $source = [$this->config->get(RestConfig::EXTENSIONS), $this->config->get(RestConfig::DISABLED_EXTENSIONS), $this->runtime->all()];

        if ($source !== $this->source) {
            $this->source = $source;
            $this->candidates = $this->candidates(self::listed($source[0]), self::listed($source[1]), $source[2]);
            $this->built = null;
            $this->applicable = [];
        }

        return $this->candidates;
    }

    /**
     * @param  list<mixed>  $configured
     * @param  list<mixed>  $disabled
     * @param  list<string>  $registered
     * @return array<string, string>
     */
    private function candidates(array $configured, array $disabled, array $registered): array
    {
        if ($configured === [] && $registered === []) {
            return [];
        }

        $own = array_filter(
            ExtensionList::of($configured, $disabled, $registered)->entries,
            fn (ExtensionEntry $entry): bool => ! $entry->disabled && (self::addsRouteMiddleware($entry->class) || $this->container->bound($entry->class)),
        );

        $named = NamedExtensions::of(array_values($own));
        $candidates = [];

        foreach ($named->refused as $exception) {
            if (array_any($own, static fn (ExtensionEntry $entry): bool => self::addsRouteMiddleware($entry->class) && $exception->concerns($entry->class))) {
                throw $exception;
            }
        }

        foreach ($named->entries as $entry) {
            if ($entry->name === null) {
                continue;
            }

            if (in_array($entry->name, $named->shared, true)) {
                self::ensureNotShared($entry->name, $named->entries);

                continue;
            }

            $candidates[$entry->name] = $entry->class;
        }

        ksort($candidates);

        return $candidates;
    }

    /** @param list<ExtensionEntry> $entries */
    private static function ensureNotShared(string $name, array $entries): void
    {
        $sharing = array_values(array_map(static fn (ExtensionEntry $entry): string => $entry->class, array_filter($entries, static fn (ExtensionEntry $entry): bool => $entry->name === $name)));

        if ($sharing !== [] && array_any($sharing, self::addsRouteMiddleware(...))) {
            throw InvalidExtensionException::sharedName($name, $sharing);
        }
    }

    /** @return list<mixed> */
    private static function listed(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
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
            $extension = self::addsRouteMiddleware($class) ? $factory->make($class, $name) : self::boundOnly($factory, $name, $class);

            if ($extension instanceof AddsRouteMiddleware) {
                $built[$name] = $extension;
            }
        }

        return $this->built = $built;
    }

    private static function boundOnly(ExtensionFactory $factory, string $name, string $class): ?Extension
    {
        try {
            return $factory->make($class, $name);
        } catch (InvalidExtensionException) {
            return null;
        }
    }

    private static function addsRouteMiddleware(string $class): bool
    {
        return is_subclass_of($class, AddsRouteMiddleware::class);
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
