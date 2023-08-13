<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\AutomaticGatingResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\NoAuthorizationResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\NoExposedFieldsResource;

class NoAuthorizationController extends Controller
{
    public static $resource = NoAuthorizationResource::class;
}