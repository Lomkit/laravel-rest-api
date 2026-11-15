<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Config\RestConfig;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionFactory;
use Lomkit\Rest\Extensions\ExtensionList;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;
use Lomkit\Rest\Extensions\NamedExtensions;

/** @internal */
final readonly class RouteEntries
{
    /**
     * @param  array<string, class-string<Extension>>  $classes
     * @param  array<string, Extension&AddsRouteMiddleware>  $bound
     */
    private function __construct(
        public array $classes,
        public array $bound,
    ) {}

    /** @param list<string> $registered */
    public static function of(Repository $config, Container $container, array $registered): self
    {
        $configured = self::configured($config);

        if ($configured === [] && $registered === []) {
            return new self([], []);
        }

        $factory = new ExtensionFactory($container);
        $routed = [];
        $bound = [];
        $unreadDisabled = null;

        try {
            $disabled = RestConfig::extensionClasses($config, RestConfig::DISABLED_EXTENSIONS);
        } catch (InvalidConfigException $exception) {
            $disabled = [];
            $unreadDisabled = $exception;
        }

        foreach (ExtensionList::of($configured, $disabled, $registered)->entries as $entry) {
            if ($entry->disabled) {
                continue;
            }

            if (self::addsRouteMiddleware($entry->class)) {
                $routed[] = $entry;
            } elseif ($container->bound($entry->class)) {
                $extension = self::built($factory, $entry->class);

                if ($extension instanceof AddsRouteMiddleware) {
                    $routed[] = $entry;
                    $bound[$entry->class] = $extension;
                }
            }
        }

        if ($unreadDisabled !== null && $routed !== []) {
            throw $unreadDisabled;
        }

        $classes = NamedExtensions::of($routed)->enabled();
        ksort($classes, SORT_STRING);

        return new self($classes, $bound);
    }

    public static function addsRouteMiddleware(mixed $class): bool
    {
        return is_string($class) && is_subclass_of($class, AddsRouteMiddleware::class);
    }

    /** @return list<mixed> */
    private static function configured(Repository $config): array
    {
        try {
            return RestConfig::extensionClasses($config, RestConfig::EXTENSIONS);
        } catch (InvalidConfigException $exception) {
            $value = $config->get(RestConfig::EXTENSIONS);

            if (is_array($value) && array_any($value, self::addsRouteMiddleware(...))) {
                throw $exception;
            }

            return [];
        }
    }

    private static function built(ExtensionFactory $factory, string $class): ?Extension
    {
        try {
            return $factory->make($class);
        } catch (InvalidExtensionException) {
            return null;
        }
    }
}
