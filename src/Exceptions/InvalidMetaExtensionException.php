<?php

namespace Lomkit\Rest\Exceptions;

use Lomkit\Rest\Http\ExtensionMetaValues;

final class InvalidMetaExtensionException extends ExtensionException
{
    public static function gatesKeyName(string $name, string $class, string $resource): self
    {
        return self::about($class, "The [{$name}] extension {$class} adds search meta to {$resource} under its name, which is the key of the gates in the meta of a search page (rest.gates.key). Set rest.gates.key to another name, or ".self::rename($class));
    }

    public static function instructionsName(string $name, string $class, string $resource): self
    {
        return self::about($class, "The [{$name}] extension {$class} adds search meta to {$resource} under its name, which is the key of the instruction meta of a search page. ".ucfirst(self::rename($class)));
    }

    public static function keyTaken(string $name, string $class, string $resource, string $method): self
    {
        return self::about($class, "The resource [{$resource}] returns the key [{$name}] from {$method}(), which holds the meta that the [{$name}] extension {$class} adds. Rename the key in {$method}() of the resource, or ".self::rename($class));
    }

    public static function notEncodable(string $name, string $class, string $resource, string $method, string $path, string $type): self
    {
        return self::about($class, "The [{$name}] extension {$class} returns {$type} at [{$path}] from {$method}() for {$resource}, which extension meta does not accept. Return null, booleans, integers, finite floats, strings, arrays, stdClass, backed enums or JsonSerializable objects; for a model, an Eloquent collection or a JSON resource, which would render every visible attribute, return the values the client needs.");
    }

    public static function notJson(string $name, string $class, string $resource, string $method, string $reason): self
    {
        return self::about($class, "The [{$name}] extension {$class} returns meta from {$method}() for {$resource} that JSON cannot encode: {$reason}. Return valid UTF-8 strings, finite numbers and values nested at most ".ExtensionMetaValues::DEPTH.' levels deep, also from jsonSerialize().');
    }

    private static function rename(string $class): string
    {
        return "give the extension another name: return it from {$class}::name(), or list a subclass of {$class} that overrides name() in the [rest.extensions] config and {$class} in the [rest.disabled_extensions] config.";
    }
}
