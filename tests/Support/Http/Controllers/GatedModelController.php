<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\GatedModelResource;

class GatedModelController extends Controller
{
    public static $resource = GatedModelResource::class;
}
