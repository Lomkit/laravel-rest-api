<?php

namespace Lomkit\Rest\Exceptions;

final class EngineWindowException extends SearchRunException
{
    public function __construct(string $message, public readonly ?int $window)
    {
        parent::__construct($message);
    }
}
