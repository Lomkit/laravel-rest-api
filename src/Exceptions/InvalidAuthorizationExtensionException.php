<?php

namespace Lomkit\Rest\Exceptions;

final class InvalidAuthorizationExtensionException extends ExtensionException
{
    public static function changedRows(string $name, string $resource, string $ability): self
    {
        return self::about($name, "The [{$name}] extension changed the rows that it prepares for the ability [{$ability}] of {$resource}: an attribute, a relation loaded before the preparation or its models, the exists flag, table, connection, key name, casts or timestamps of a row, or the list of rows. The rows are the rendered models: read what the policies need into a store of the extension, keyed by user and row key, and leave the rows as they are.");
    }

    public static function invalidContextKey(string $name, string $resource, string $key): self
    {
        return self::about($name, "The [{$name}] extension puts the cache context key [{$key}] for {$resource}. A key has 1 to 64 letters, digits or underscores and starts with a letter, such as [tenant]; the package keys it as [{$name}.tenant].");
    }

    public static function invalidContextValue(string $name, string $resource, string $key): self
    {
        return self::about($name, "The [{$name}] extension puts a float that is not finite under the cache context key [{$key}] for {$resource}. Put an integer, a finite float, a string, a boolean or null.");
    }

    public static function contextKeyTaken(string $name, string $resource, string $key): self
    {
        return self::about($name, "The [{$name}] extension puts the cache context key [{$key}], which {$resource} already returns from authorizationCacheContext(). Rename the key of the resource or of the extension.");
    }

    public static function invalidCacheDuration(string $name, string $resource, string $ability, int $seconds): self
    {
        return self::about($name, "The [{$name}] extension shortens the cache of the ability [{$ability}] of {$resource} to {$seconds} seconds. Give 1 second or more, or call forget('{$ability}') to keep no decision.");
    }

    public static function invalidGate(string $name, string $class, string $resource, int $index, mixed $gate): self
    {
        if (! is_string($gate)) {
            $type = get_debug_type($gate);

            return self::about($name, "The [{$name}] extension {$class} returns {$type} at index {$index} from gates() for {$resource}. Return the names of policy methods.");
        }

        return self::about($name, "The [{$name}] extension {$class} declares the gate [{$gate}] at index {$index} for {$resource}. A gate is the camelCase name of a policy method, listed once by each extension, other than viewAny and the built-in gates view, create, update, delete, restore and forceDelete.");
    }
}
