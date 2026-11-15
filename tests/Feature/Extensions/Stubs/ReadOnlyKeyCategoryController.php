<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class ReadOnlyKeyCategoryController extends Controller
{
    public static string $resource = ReadOnlyKeyCategoryResource::class;
}
