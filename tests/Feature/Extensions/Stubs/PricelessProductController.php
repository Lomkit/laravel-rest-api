<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class PricelessProductController extends Controller
{
    public static string $resource = PricelessProductResource::class;
}
