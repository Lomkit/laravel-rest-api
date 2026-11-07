<?php

namespace Lomkit\Rest\Tests\Feature\Mutations\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class RequiringAuthorController extends Controller
{
    public static string $resource = RequiringAuthorResource::class;
}
