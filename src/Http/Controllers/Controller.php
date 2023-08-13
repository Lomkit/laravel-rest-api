<?php

namespace Lomkit\Rest\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Concerns\Authorizable;
use Lomkit\Rest\Concerns\PerformsModelOperations;
use Lomkit\Rest\Concerns\PerformsRestOperations;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Concerns\PerformsQueries;
use Lomkit\Rest\Http\Resource;

abstract class Controller extends \Illuminate\Routing\Controller
{
    use PerformsRestOperations;

    /**
     * The resource the entry corresponds to.
     *
     * @var class-string<Resource>
     */
    public static $resource;

    //@TODO: voir si toutes ces manières de call en static c'est pas plus dérangeant qu'autre chose
    //@TODO: surtout avec octane où il faut éviter le static !?
    /**
     * Get a fresh instance of the resource represented by the entry.
     *
     * @return Resource
     */
    public static function newResource(): Resource
    {
        /** @var Resource $resource */
        $resource = static::$resource;

        return new $resource;
    }

    //@TODO: are controllers useless in a certain way ??
}