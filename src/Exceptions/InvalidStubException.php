<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidStubException extends LogicException
{
    public static function missingPlaceholder(string $path, string $placeholder): self
    {
        return new self("The stub [{$path}] does not contain the placeholder {$placeholder}.");
    }
}
