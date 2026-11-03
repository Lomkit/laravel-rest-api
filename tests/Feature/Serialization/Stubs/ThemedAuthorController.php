<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class ThemedAuthorController extends Controller
{
    public static string $resource = ThemedAuthorResource::class;
}
