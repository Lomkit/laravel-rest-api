<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidResourceException extends LogicException
{
    public static function missingModel(string $resource): self
    {
        return new self("The resource [{$resource}] does not declare its model. Add public static string \$model = YourModel::class; to it.");
    }

    public static function missingIndexPerimeter(string $resource): self
    {
        return new self("The resource [{$resource}] cannot run a text search: searchScoutQuery() is not declared in the class that declares searchQuery() or in a subclass of it. Declare searchScoutQuery() there with the same constraints as searchQuery(), as where() conditions on indexed, filterable attributes.");
    }
}
