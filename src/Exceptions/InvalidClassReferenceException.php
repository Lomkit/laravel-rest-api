<?php

namespace Lomkit\Rest\Exceptions;

use InvalidArgumentException;

final class InvalidClassReferenceException extends InvalidArgumentException
{
    public static function invalidName(string $name): self
    {
        return new self("[{$name}] is not a valid class name.");
    }
}
