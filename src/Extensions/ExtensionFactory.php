<?php

namespace Lomkit\Rest\Extensions;

use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Extensions\Kinds\ExtensionKind;

/** @internal */
final readonly class ExtensionFactory
{
    public function __construct(private Container $container) {}

    public function make(string $class, ?string $name = null): Extension
    {
        $class = ExtensionClass::ensureValid($class);

        if (! array_any(class_implements($class), static fn (string $interface): bool => is_subclass_of($interface, ExtensionKind::class))) {
            throw InvalidExtensionException::noKind($class, $name);
        }

        $extension = $this->container->make($class);

        if (! $extension instanceof $class) {
            throw InvalidExtensionException::foreignBinding($class, get_debug_type($extension), $name);
        }

        return $extension;
    }
}
