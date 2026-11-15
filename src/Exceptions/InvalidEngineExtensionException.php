<?php

namespace Lomkit\Rest\Exceptions;

final class InvalidEngineExtensionException extends InvalidExtensionException
{
    public static function conflictingProfiles(string $engine, string $resource, string $first, string $firstClass, string $second, string $secondClass): self
    {
        return self::about($second, "The [{$first}] extension {$firstClass} and the [{$second}] extension {$secondClass} both provide the engine profile of [{$engine}] for {$resource}. Let one of them provide it: return null from engineProfile() of the other for this engine, or disable one of them in rest.extensions.");
    }
}
