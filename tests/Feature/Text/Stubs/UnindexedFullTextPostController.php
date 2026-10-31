<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class UnindexedFullTextPostController extends Controller
{
    public static string $resource = UnindexedFullTextPostResource::class;
}
