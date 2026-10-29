<?php

namespace Lomkit\Rest\Tests\Fixtures\Library;

use Lomkit\Rest\Http\Controllers\Controller;

class BookController extends Controller
{
    public static string $resource = BookResource::class;
}
