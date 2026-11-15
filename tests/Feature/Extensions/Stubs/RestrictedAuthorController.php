<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class RestrictedAuthorController extends Controller
{
    public static string $resource = RestrictedAuthorResource::class;
}
