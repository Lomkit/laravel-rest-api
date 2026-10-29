<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidResourceException extends LogicException
{
    public static function missingModel(string $resource): self
    {
        return new self("The resource [{$resource}] does not declare its model. Add public static string \$model = YourModel::class; to it.");
    }
}
