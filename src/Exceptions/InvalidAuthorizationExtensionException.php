<?php

namespace Lomkit\Rest\Exceptions;

final class InvalidAuthorizationExtensionException extends InvalidExtensionException
{
    public static function changedRows(string $name, string $resource, string $ability): self
    {
        return self::about($name, "The [{$name}] extension changed the rows that it prepares for the ability [{$ability}] of {$resource}: an attribute, a relation that was loaded, or the list of rows. The rows are the rendered models: read what the policies need into a store of the extension, keyed by user and row key, and leave the rows as they are.");
    }
}
