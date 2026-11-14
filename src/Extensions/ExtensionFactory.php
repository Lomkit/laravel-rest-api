<?php

namespace Lomkit\Rest\Extensions;

use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\Kinds\ExtensionKind;
use ReflectionClass;

final readonly class ExtensionFactory
{
    public function __construct(private Container $container) {}

    public function make(string $name, string $class): Extension
    {
        $class = $this->buildable($name, $class);
        $extension = $this->container->make($class);

        if (! $extension instanceof $class) {
            throw InvalidExtensionException::foreignBinding($name, $class, get_debug_type($extension));
        }

        return $extension;
    }

    /** @return class-string<Extension> */
    private function buildable(string $name, string $class): string
    {
        if (! class_exists($class)) {
            throw InvalidExtensionException::notAClass($name, $class);
        }

        if (! is_subclass_of($class, Extension::class)) {
            throw InvalidExtensionException::notAnExtension($name, $class);
        }

        $reflection = new ReflectionClass($class);

        if (! array_any($reflection->getInterfaceNames(), static fn (string $interface): bool => is_subclass_of($interface, ExtensionKind::class))) {
            throw InvalidExtensionException::noKind($name, $class);
        }

        if (! $reflection->isInstantiable()) {
            throw InvalidExtensionException::notInstantiable($name, $class);
        }

        return $class;
    }
}
