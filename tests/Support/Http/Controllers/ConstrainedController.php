<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\ConstrainedResource;

class ConstrainedController extends Controller
{
    public static $resource = ConstrainedResource::class;
}
