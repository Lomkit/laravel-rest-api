<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class RuledScopePostController extends Controller
{
    public static string $resource = RuledScopePostResource::class;
}
