<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Support\Arr;
use LogicException;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionName;
use Lomkit\Rest\Extensions\Kinds\ExtensionKind;

final class InvalidExtensionException extends LogicException
{
    private ?string $extension = null;

    public static function missingName(string $entry): self
    {
        return new self("The extension [{$entry}] has no name. Key it by its name, such as 'acl' => [{$entry}], in the [rest.extensions] config or in Rest::registerExtensions().");
    }

    public static function unnamedEntry(int $index, mixed $value): self
    {
        if (is_object($value)) {
            $class = $value::class;

            return new self("The extension entry at index [{$index}] is an instance of {$class} and has no name. Key its class name by the extension name, such as 'acl' => {$class}::class, in the [rest.extensions] config or in Rest::registerExtensions().");
        }

        $type = get_debug_type($value);

        return new self("The extension entry at index [{$index}] is [{$type}] and has no name. Key the class name of each extension by its name, such as 'acl' => AccessControlExtension::class, in the [rest.extensions] config or in Rest::registerExtensions(); only the config accepts 'acl' => false, to disable an extension.");
    }

    public static function invalidName(string $name): self
    {
        $length = ExtensionName::MAX_LENGTH;

        return self::about($name, "The extension name [{$name}] is invalid. A name has 1 to {$length} lowercase letters or digits, starts with a letter and joins words with single underscores, such as [acl] or [row_lock]: it prefixes the keys that the extension adds, and two underscores separate it from them.");
    }

    /** @param non-empty-list<string> $classes */
    public static function sharedName(string $name, array $classes): self
    {
        $registered = Arr::join($classes, ', ', ' and ');

        return self::about($name, "The [{$name}] extension name is registered by several packages, as {$registered}. Settle it in the [rest.extensions] config: '{$name}' => {$classes[0]}::class keeps one, '{$name}' => false keeps none, and the other class can take another name there.");
    }

    public static function lateRegistration(string $name, string $class): self
    {
        return self::about($name, "The [{$name}] extension {$class} is registered after the application booted, after the package built its first context or registered its first Rest::resource() route: the runtime extension list no longer changes then. Register extensions from the register() method of a service provider, which runs before the routes in any provider order. Register from boot() only before every provider that declares Rest::resource() routes in its own boot().");
    }

    public static function notAClass(string $name, string $entry): self
    {
        return self::about($name, "The [{$name}] extension is [{$entry}], which is not a class. Give the name of a class that extends ".Extension::class.'; only the [rest.extensions] config accepts false, to disable the extension.');
    }

    public static function instanceGiven(string $name, string $class): self
    {
        return self::about($name, "The [{$name}] extension is an instance of {$class}. Register its class name, {$class}::class: the container builds each extension once per context, and an instance would outlive the context.");
    }

    public static function notAnExtension(string $name, string $class): self
    {
        return self::about($name, "The [{$name}] extension {$class} does not extend ".Extension::class.'. Extend it.');
    }

    public static function noKind(string $name, string $class): self
    {
        return self::about($name, "The [{$name}] extension {$class} implements no extension kind. Implement at least one interface that extends ".ExtensionKind::class.'.');
    }

    public static function notInstantiable(string $name, string $class): self
    {
        return self::about($name, "The [{$name}] extension {$class} is abstract or cannot be instantiated. Register a concrete class.");
    }

    public static function foreignBinding(string $name, string $class, string $bound): self
    {
        return self::about($name, "The container builds [{$bound}] for {$class}, the [{$name}] extension: bind {$class} to itself or to a class that extends it.");
    }

    public function namesExtension(string $name): bool
    {
        return $this->extension === $name;
    }

    private static function about(string $name, string $message): self
    {
        $exception = new self($message);
        $exception->extension = $name;

        return $exception;
    }
}
