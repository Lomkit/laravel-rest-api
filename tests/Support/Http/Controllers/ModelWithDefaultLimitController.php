<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelWithDefaultLimitResource;

class ModelWithDefaultLimitController extends Controller
{
    public static $resource = ModelWithDefaultLimitResource::class;
}
