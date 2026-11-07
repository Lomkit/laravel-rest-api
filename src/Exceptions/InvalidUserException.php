<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidUserException extends LogicException
{
    public static function unusableIdentifier(string $user, mixed $identifier): self
    {
        $type = get_debug_type($identifier);

        return new self("The user [{$user}] returns a value of type [{$type}] from getAuthIdentifier(). Return a scalar, a Stringable or null.");
    }
}
