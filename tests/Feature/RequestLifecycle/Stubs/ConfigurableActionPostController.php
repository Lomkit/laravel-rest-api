<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class ConfigurableActionPostController extends Controller
{
    public static string $resource = ConfigurableActionPostResource::class;
}
