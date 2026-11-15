<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class PreparedTagController extends Controller
{
    public static string $resource = PreparedTagResource::class;
}
