<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Support\Translation;

/** @internal */
final class InvalidEngineValueException extends InvalidEngineQueryException
{
    public function __construct(string $message, private readonly string $translation)
    {
        parent::__construct($message);
    }

    public function refusal(string $key): ValidationException
    {
        return ValidationException::withMessages([$key => [Translation::line($this->translation, ['attribute' => $key])]]);
    }
}
