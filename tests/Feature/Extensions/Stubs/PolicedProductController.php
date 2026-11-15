<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class PolicedProductController extends Controller
{
    public static string $resource = PolicedProductResource::class;
}
