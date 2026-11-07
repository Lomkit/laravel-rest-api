<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class ContextCheckingPostController extends Controller
{
    public static string $resource = ContextCheckingPostResource::class;
}
