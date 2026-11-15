<?php

namespace Lomkit\Rest\Extensions;

use Lomkit\Rest\Exceptions\InvalidExtensionException;
use ReflectionClass;

/** @internal */
final readonly class ExtensionClass
{
    /** @return class-string<Extension> */
    public static function ensureValid(string $class): string
    {
        $class = ltrim($class, '\\');

        if (! class_exists($class)) {
            throw InvalidExtensionException::notAClass($class);
        }

        if (! is_subclass_of($class, Extension::class)) {
            throw InvalidExtensionException::notAnExtension($class);
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable()) {
            throw InvalidExtensionException::notInstantiable($reflection->getName());
        }

        return $reflection->getName();
    }

    public static function canonical(string $class): string
    {
        return class_exists($class) ? (new ReflectionClass($class))->getName() : ltrim($class, '\\');
    }
}
