<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class UndeclaredModelController extends Controller
{
    public static string $resource = UndeclaredModelResource::class;
}
