<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\SoftDeletedModelResource;

class SoftDeletedModelController extends Controller
{
    public static $resource = SoftDeletedModelResource::class;
}
