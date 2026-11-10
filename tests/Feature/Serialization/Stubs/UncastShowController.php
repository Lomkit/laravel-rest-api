<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class UncastShowController extends Controller
{
    public static string $resource = UncastShowResource::class;
}
