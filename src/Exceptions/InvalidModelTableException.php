<?php

namespace Lomkit\Rest\Exceptions;

use InvalidArgumentException;

final class InvalidModelTableException extends InvalidArgumentException
{
    public static function noModel(): self
    {
        return new self('No model was given or found for this resource. Pass --model to generate its fields.');
    }

    public static function missingModel(string $class): self
    {
        return new self("The model [{$class}] does not exist.");
    }

    public static function notAModel(string $class): self
    {
        return new self("The class [{$class}] is not an Eloquent model.");
    }

    public static function notInstantiable(string $class): self
    {
        return new self("The model [{$class}] cannot be instantiated. Pass a concrete model.");
    }

    public static function missingTable(string $table, string $class): self
    {
        return new self("The table [{$table}] of the model [{$class}] does not exist. Run the migrations, then generate the resource again.");
    }
}
