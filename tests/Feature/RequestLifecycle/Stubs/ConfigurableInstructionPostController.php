<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class ConfigurableInstructionPostController extends Controller
{
    public static string $resource = ConfigurableInstructionPostResource::class;
}
