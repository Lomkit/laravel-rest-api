<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class ClonedCountBookController extends Controller
{
    public static string $resource = ClonedCountBookResource::class;
}
