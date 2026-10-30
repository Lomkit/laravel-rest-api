<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class ConstrainedPostController extends Controller
{
    public static string $resource = ConstrainedPostResource::class;
}
