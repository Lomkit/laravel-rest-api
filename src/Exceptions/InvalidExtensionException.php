<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Support\Arr;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionName;
use Lomkit\Rest\Extensions\Kinds\ExtensionKind;
use Throwable;

final class InvalidExtensionException extends ExtensionException
{
    public static function invalidName(string $class, string $name): self
    {
        $length = ExtensionName::MAX_LENGTH;

        return self::about($class, "{$class}::name() returns [{$name}], which is not a valid extension name. A name has 1 to {$length} lowercase letters or digits, starts with a letter and joins words with single underscores, such as [acl] or [row_lock]: it prefixes the keys that the extension adds, and two underscores separate it from them. Return a name such as 'acl' from {$class}::name().");
    }

    public static function unreadableName(string $class, Throwable $previous): self
    {
        $type = $previous::class;

        return self::about($class, "The extension {$class} gives no name: {$class}::name() throws [{$type}] {$previous->getMessage()} Return a name such as 'acl' from {$class}::name(), without reading anything that can fail.", $previous);
    }

    /** @param non-empty-list<string> $classes */
    public static function sharedName(string $name, array $classes): self
    {
        $named = Arr::join($classes, ', ', ' and ');

        return new self("The extensions {$named} share the name [{$name}]. Add one of them to the [rest.disabled_extensions] config, or extend one of them and override name() to give it another name.");
    }

    public static function lateRegistration(string $class): self
    {
        return self::about($class, "The extension {$class} is registered after the application booted, or after the package built its first context or registered its first Rest::resource() route: the runtime extension list no longer changes then. Register extensions from the register() method of a service provider, which runs before the routes in any provider order. Register from boot() only before every provider that declares Rest::resource() routes in its own boot().");
    }

    public static function notAList(string $source): self
    {
        return new self("{$source} takes a list of extension class names, such as [AccessControlExtension::class]: each extension class declares its own name.");
    }

    public static function notAClassName(string $source, string $type): self
    {
        return new self("{$source} holds [{$type}], which is not a class name. Give a list of extension class names, such as [AccessControlExtension::class].");
    }

    public static function instanceGiven(string $source, string $class): self
    {
        return self::about($class, "{$source} holds an instance of {$class}. Give its class name in the list, such as [{$class}::class]: the container builds each extension once per context, and an instance would outlive the context.");
    }

    public static function notAClass(string $class): self
    {
        return self::about($class, "The extension [{$class}] is not a class. Give the name of a class that extends ".Extension::class.'.');
    }

    public static function notAnExtension(string $class): self
    {
        return self::about($class, "The extension {$class} does not extend ".Extension::class.'. Extend it.');
    }

    public static function notInstantiable(string $class): self
    {
        return self::about($class, "The extension {$class} is abstract or cannot be instantiated. Register a concrete class.");
    }

    public static function noKind(string $class, ?string $name = null): self
    {
        return self::about($class, self::subject($class, $name).' implements no extension kind. Implement at least one interface that extends '.ExtensionKind::class.'.');
    }

    public static function foreignBinding(string $class, string $bound, ?string $name = null): self
    {
        return self::about($class, "The container builds [{$bound}] for ".lcfirst(self::subject($class, $name)).": bind {$class} to itself or to a class that extends it.");
    }

    private static function subject(string $class, ?string $name): string
    {
        return $name === null ? "The extension {$class}" : "The [{$name}] extension {$class}";
    }
}
