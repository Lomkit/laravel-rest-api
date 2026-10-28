<?php

namespace Lomkit\Rest\Http\Controllers;

use Lomkit\Rest\Concerns\PerformsRestOperations;
use Lomkit\Rest\Http\Controllers\Traits\ExtendsDocumentationOperations;
use Lomkit\Rest\Http\Controllers\Traits\HasControllerHooks;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

abstract class Controller extends \Illuminate\Routing\Controller
{
    use ExtendsDocumentationOperations;
    use HasControllerHooks;
    use PerformsRestOperations;

    /**
     * The resource the entry corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource;

    public static function newResource(): Resource
    {
        return app(RestRequest::class)->resources()->get(static::$resource);
    }
}
