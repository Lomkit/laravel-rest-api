<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class AliasFieldAuthorController extends Controller
{
    public static string $resource = AliasFieldAuthorResource::class;
}
