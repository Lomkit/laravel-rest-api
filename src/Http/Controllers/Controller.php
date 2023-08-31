<?php

namespace Lomkit\Rest\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Concerns\Authorizable;
use Lomkit\Rest\Concerns\PerformsModelOperations;
use Lomkit\Rest\Concerns\PerformsRestOperations;
use Lomkit\Rest\Http\Controllers\Traits\ExtendsDocumentationOperations;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Concerns\PerformsQueries;
use Lomkit\Rest\Http\Resource;

abstract class Controller extends \Illuminate\Routing\Controller
{
    use PerformsRestOperations, ExtendsDocumentationOperations;

    /**
     * The resource the entry corresponds to.
     *
     * @var class-string<Resource>
     */
    public static $resource;

    /**
     * Get a fresh instance of the resource represented by the entry.
     *
     * @return Resource
     */
    public static function newResource(): Resource
    {
        $resource = static::$resource;

        return new $resource;
    }
}