<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidControllerException extends LogicException
{
    public static function missingResource(string $controller): self
    {
        return new self("The controller [{$controller}] does not declare its resource. Add public static string \$resource = YourResource::class; to it.");
    }
}
