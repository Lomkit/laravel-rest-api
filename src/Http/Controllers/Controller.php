<?php

namespace Lomkit\Rest\Http\Controllers;

use Lomkit\Rest\Concerns\PerformsRestOperations;
use Lomkit\Rest\Http\Controllers\Traits\ExtendsDocumentationOperations;
use Lomkit\Rest\Http\Resource;

abstract class Controller extends \Illuminate\Routing\Controller
{
    use PerformsRestOperations;
    use ExtendsDocumentationOperations;

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

        return new $resource();
    }
}
