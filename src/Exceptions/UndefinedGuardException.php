<?php

namespace Lomkit\Rest\Exceptions;

use InvalidArgumentException;

final class UndefinedGuardException extends InvalidArgumentException
{
    public static function for(string $guard): self
    {
        return new self("The [{$guard}] guard is not defined in config/auth.php.");
    }
}
