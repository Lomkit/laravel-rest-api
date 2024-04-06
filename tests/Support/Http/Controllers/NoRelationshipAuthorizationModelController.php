<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\NoRelationshipAuthorizationModelResource;

class NoRelationshipAuthorizationModelController extends Controller
{
    public static $resource = NoRelationshipAuthorizationModelResource::class;
}
