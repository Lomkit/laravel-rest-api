<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidConfirmationException extends LogicException
{
    public static function dataList(): self
    {
        return new self('The data of a Confirmation is a list, but the answer gives it as a JSON object: pass an array with string keys, such as [\'rows\' => 37].');
    }

    public static function expiry(int $seconds): self
    {
        return new self("A Confirmation expires after one second at least, but expiresIn() received {$seconds}.");
    }
}
