<?php

namespace Lomkit\Rest\Tests\Feature\Scramble\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Tests\Fixtures\Publishing\TagResource;

class TagController extends Controller
{
    public static string $resource = TagResource::class;
}
