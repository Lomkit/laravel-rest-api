<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelWithHooksResource;

class ModelHooksController extends Controller
{
    public static string $resource = ModelWithHooksResource::class;
}
