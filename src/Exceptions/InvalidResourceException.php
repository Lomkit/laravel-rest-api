<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class InvalidResourceException extends LogicException
{
    public static function missingModel(string $resource): self
    {
        return new self("The resource [{$resource}] does not declare its model. Add public static string \$model = YourModel::class; to it.");
    }

    public static function removedHook(string $resource, string $method, string $replacement): self
    {
        return new self("The resource [{$resource}] declares {$method}(), which the package no longer calls. {$replacement}");
    }

    public static function unsearchableModel(string $model): self
    {
        return new self("The model [{$model}] is not indexed: it does not use Laravel\\Scout\\Searchable.");
    }

    public static function invalidScoutAttribute(string $model, string $method): self
    {
        return new self("The model [{$model}] returns an invalid value from {$method}().");
    }

    public static function integerScoutKey(string $model, mixed $key): self
    {
        $value = is_scalar($key) ? var_export($key, true) : get_debug_type($key);

        return new self("The scout key {$value} of the model [{$model}] is not an integer. Override getScoutKeyType() to return 'string' when the scout keys of the model are not integers.");
    }

    public static function invalidScoutKey(string $model, mixed $key): self
    {
        $type = get_debug_type($key);

        return new self("A scout key of the model [{$model}] is a value of type [{$type}]. Scout keys are strings or integers.");
    }
}
