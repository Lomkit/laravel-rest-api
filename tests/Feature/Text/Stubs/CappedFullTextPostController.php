<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class CappedFullTextPostController extends Controller
{
    public static string $resource = CappedFullTextPostResource::class;
}
