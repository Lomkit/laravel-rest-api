<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidScopeException extends LogicException
{
    public static function notAScope(string $owner, int $index, mixed $entry): self
    {
        if (is_string($entry)) {
            return new self("{$owner} returned a string at index {$index} ('{$entry}'). Return Scope objects, for example Scope::make('{$entry}').");
        }

        return new self("{$owner} returned ".get_debug_type($entry)." at index {$index}. Return Scope objects.");
    }

    public static function undeclared(string $resource, string $name): self
    {
        return new self("{$resource} does not declare the scope [{$name}].");
    }
}
