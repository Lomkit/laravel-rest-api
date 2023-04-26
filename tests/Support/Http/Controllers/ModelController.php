<?php

namespace Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Support\Rest\Resources\ModelResource;

class ModelController extends Controller
{
    public static $resource = ModelResource::class;
}