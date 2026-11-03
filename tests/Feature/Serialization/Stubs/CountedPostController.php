<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class CountedPostController extends Controller
{
    public static string $resource = CountedPostResource::class;
}
