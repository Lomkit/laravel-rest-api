<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;
use Throwable;

/** @phpstan-consistent-constructor */
abstract class ExtensionException extends LogicException
{
    private ?string $extension = null;

    public function concerns(string $class): bool
    {
        return $this->extension === $class;
    }

    protected static function about(string $class, string $message, ?Throwable $previous = null): static
    {
        $exception = new static($message, 0, $previous);
        $exception->extension = $class;

        return $exception;
    }
}
