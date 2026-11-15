<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class UncappedGroupedBookController extends Controller
{
    public static string $resource = UncappedGroupedBookResource::class;
}
