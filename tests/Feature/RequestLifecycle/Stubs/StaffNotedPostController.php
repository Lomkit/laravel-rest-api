<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class StaffNotedPostController extends Controller
{
    public static string $resource = StaffNotedPostResource::class;
}
