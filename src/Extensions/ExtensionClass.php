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
        if (! class_exists($class)) {
            throw InvalidExtensionException::notAClass($class);
        }

        if (! is_subclass_of($class, Extension::class)) {
            throw InvalidExtensionException::notAnExtension($class);
        }

        if (! (new ReflectionClass($class))->isInstantiable()) {
            throw InvalidExtensionException::notInstantiable($class);
        }

        return $class;
    }
}
