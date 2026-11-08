<?php

namespace Lomkit\Rest\Exceptions;

use InvalidArgumentException;

final class InvalidConfigException extends InvalidArgumentException
{
    public static function mustBe(string $key, string $type): self
    {
        return new self("The [{$key}] option must be {$type}.");
    }

    public static function missingAppKey(): self
    {
        return new self('An action asks for a confirmation, but the [app.key] option is empty: the confirmation token is signed with the application key. Set APP_KEY, for example with php artisan key:generate.');
    }

    public static function unknown(string $key): self
    {
        return new self("The [{$key}] option does not exist.");
    }
}
