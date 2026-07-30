<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\OrPerimeterSoftDeletedModelResource;

class OrPerimeterSoftDeletedModelController extends Controller
{
    public static $resource = OrPerimeterSoftDeletedModelResource::class;
}
