<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidInstructionException extends LogicException
{
    public static function engineOnly(string $instruction): self
    {
        return new self("{$instruction} implements AppliesToEngine without its own handle(): it only applies to a text search on a search engine.");
    }

    public static function queryOnly(string $instruction): self
    {
        return new self("{$instruction} does not implement AppliesToEngine: it cannot apply to a text search on a search engine.");
    }
}
