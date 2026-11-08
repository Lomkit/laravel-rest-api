<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class SearchRunException extends LogicException
{
    public static function missingUser(string $resource): self
    {
        return new self("The search of the resource [{$resource}] has no user. Call as() with the user, or as(null) to search as a guest.");
    }
}
