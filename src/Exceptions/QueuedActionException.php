<?php

namespace Lomkit\Rest\Exceptions;

use RuntimeException;

final class QueuedActionException extends RuntimeException
{
    public static function missingUser(string $guard, string $class, int|string $id): self
    {
        return new self("The queued action cannot find the user [{$class}] with the identifier [{$id}] through the [{$guard}] guard.");
    }

    public static function unidentifiedUser(string $guard, string $class): self
    {
        return new self("The action cannot be queued for the user [{$class}] of the [{$guard}] guard: its identifier is not an integer or a string.");
    }

    public static function missingAction(string $resource, string $action, string $uriKey): self
    {
        return new self("The resource [{$resource}] no longer declares the action [{$action}] under the key [{$uriKey}].");
    }
}
