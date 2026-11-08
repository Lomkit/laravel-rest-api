<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidDocumentationException extends LogicException
{
    public static function componentTaken(string $type, string $name, string $owner, string $other): self
    {
        return new self("The OpenAPI {$type} component [{$name}] of [{$owner}] is already defined differently by [{$other}]. Give one of them another name.");
    }
}
