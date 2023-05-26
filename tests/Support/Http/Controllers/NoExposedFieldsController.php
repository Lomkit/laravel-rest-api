<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\NoExposedFieldsResource;

class NoExposedFieldsController extends Controller
{
    public static $resource = NoExposedFieldsResource::class;
}