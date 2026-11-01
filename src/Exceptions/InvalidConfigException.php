<?php

namespace Lomkit\Rest\Exceptions;

use InvalidArgumentException;

final class InvalidConfigException extends InvalidArgumentException
{
    public static function mustBe(string $key, string $type): self
    {
        return new self("The [{$key}] option must be {$type}.");
    }

    public static function moved(string $key, string $to): self
    {
        return new self("The [{$key}] option moved to [{$to}].");
    }

    public static function unknown(string $key): self
    {
        return new self("The [{$key}] option does not exist.");
    }
}
