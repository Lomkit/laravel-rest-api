<?php

namespace Lomkit\Rest\Http\Controllers;

use Lomkit\Rest\Concerns\PerformsRestOperations;
use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Http\Controllers\Traits\HasControllerHooks;
use Lomkit\Rest\Http\Resource;

abstract class Controller
{
    use HasControllerHooks;
    use PerformsRestOperations;

    /** @var class-string<Resource> */
    public static string $resource;

    /** @return class-string<Resource> */
    public static function resourceClass(): string
    {
        if (! isset(static::$resource)) {
            throw InvalidControllerException::missingResource(static::class);
        }

        return static::$resource;
    }
}
