<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\AutomaticGatingResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\NoExposedFieldsResource;

class AutomaticGatingController extends Controller
{
    public static $resource = AutomaticGatingResource::class;
}