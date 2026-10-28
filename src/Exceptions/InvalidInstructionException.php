<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidInstructionException extends LogicException
{
    public static function undeclared(string $resource, string $name): self
    {
        return new self("{$resource} does not declare the instruction [{$name}].");
    }

    public static function undeclaredForScout(string $resource, string $name): self
    {
        return new self("{$resource} does not declare the Scout instruction [{$name}].");
    }

    public static function scoutNotSupported(string $instruction): self
    {
        return new self("{$instruction} does not support Scout queries. Override handleScout() to support them.");
    }
}
