<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class ReducedProductController extends Controller
{
    public static string $resource = ReducedProductResource::class;
}
