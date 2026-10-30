<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class WriteOnlyAuthorController extends Controller
{
    public static string $resource = WriteOnlyAuthorResource::class;
}
