<?php

namespace Lomkit\Rest\Http\Controllers;

use Lomkit\Rest\Concerns\PerformsRestOperations;
use Lomkit\Rest\Http\Controllers\Traits\ExtendsDocumentationOperations;
use Lomkit\Rest\Http\Controllers\Traits\HasControllerHooks;
use Lomkit\Rest\Http\Resource;

abstract class Controller extends \Illuminate\Routing\Controller
{
    use PerformsRestOperations;
    use ExtendsDocumentationOperations;
    use HasControllerHooks;

    /**
     * The resource the entry corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource;

    /**
     * Get a fresh instance of the resource represented by the entry.
     *
     * @return resource
     */
    public static function newResource(): Resource
    {
        $resource = static::$resource;

        // If the resource isn't registered, do it
        if (!app()->has($resource)) {
            app()->singleton($resource);
        }

        return app()->make($resource);
    }
}
