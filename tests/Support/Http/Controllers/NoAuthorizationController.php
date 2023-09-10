<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\NoAuthorizationResource;

class NoAuthorizationController extends Controller
{
    public static $resource = NoAuthorizationResource::class;
}
