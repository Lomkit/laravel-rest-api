<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;

class RestrictedLabelController extends Controller
{
    public static string $resource = RestrictedLabelResource::class;
}
