<?php

namespace Lomkit\Rest\Exceptions;

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

    public static function invalidName(string $name): self
    {
        $length = ExtensionName::MAX_LENGTH;

        return self::about($name, "The extension name [{$name}] is invalid. A name has 1 to {$length} lowercase letters, digits or underscores and starts with a letter, such as [acl]: it prefixes the keys that the extension adds.");
    }

    public static function duplicateName(string $name, string $registered, string $class): self
    {
        return self::about($name, "The [{$name}] extension is already registered as {$registered}, so {$class} cannot take the same name. Register {$class} under another name.");
    }

    public static function lateRegistration(string $name, string $class): self
    {
        return self::about($name, "The [{$name}] extension {$class} is registered after the package built its first context, and the extension list no longer changes then. Register extensions from the register() or boot() method of a service provider, before any request, search or Rest::resource() route.");
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
