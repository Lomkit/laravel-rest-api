<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

final class IndexedBookController extends Controller
{
    public static string $resource = IndexedBookResource::class;
}
