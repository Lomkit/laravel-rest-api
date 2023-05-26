<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class ModelController extends Controller
{
    public static $resource = ModelResource::class;
}