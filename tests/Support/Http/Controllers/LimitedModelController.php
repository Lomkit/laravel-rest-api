<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\LimitedModelResource;

class LimitedModelController extends Controller
{
    public static $resource = LimitedModelResource::class;
}
