<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class TrackedAuthorController extends Controller
{
    public static string $resource = TrackedAuthorResource::class;
}
