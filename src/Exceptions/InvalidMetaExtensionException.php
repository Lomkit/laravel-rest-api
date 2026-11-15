<?php

namespace Lomkit\Rest\Exceptions;

final class InvalidMetaExtensionException extends InvalidExtensionException
{
    public static function gatesKeyName(string $name, string $class, string $resource): self
    {
        return self::about($name, "The [{$name}] extension {$class} adds search meta to {$resource} under its name, which is the key of the gates in the meta of a search page (rest.gates.key). Register the extension under another name, or set rest.gates.key to another name.");
    }

    public static function instructionsName(string $name, string $class, string $resource): self
    {
        return self::about($name, "The [{$name}] extension {$class} adds search meta to {$resource} under its name, which is the key of the instruction meta of a search page. Register the extension under another name.");
    }

    public static function keyTaken(string $name, string $class, string $resource, string $method): self
    {
        return self::about($name, "The resource [{$resource}] returns the key [{$name}] from {$method}(), which holds the meta that the [{$name}] extension {$class} adds. Rename the key in {$method}() of the resource, or register the extension under another name.");
    }

    public static function notEncodable(string $name, string $class, string $resource, string $method, string $path, string $type): self
    {
        return self::about($name, "The [{$name}] extension {$class} returns {$type} at [{$path}] from {$method}() for {$resource}, which the response cannot encode as JSON. Return null, booleans, integers, finite floats, strings, arrays, stdClass, backed enums or JsonSerializable objects.");
    }
}
