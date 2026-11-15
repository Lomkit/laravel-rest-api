<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class RestrictedPostController extends Controller
{
    public static string $resource = RestrictedPostResource::class;
}
